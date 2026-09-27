<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class CompetencyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data): void {
            $competency = Competency::create($this->competencyData($data));
            $this->replaceLevels($competency, $data['levels']);
        });

        return back()->with('success', 'บันทึกสมรรถนะเรียบร้อยแล้ว');
    }

    public function update(Request $request, Competency $competency): RedirectResponse
    {
        $data = $this->validatedData($request, $competency);

        DB::transaction(function () use ($competency, $data): void {
            $competency->update($this->competencyData($data));
            $this->replaceLevels($competency, $data['levels']);
        });

        return back()->with('success', 'อัปเดตสมรรถนะเรียบร้อยแล้ว');
    }

    public function destroy(Competency $competency): RedirectResponse
    {
        try {
            DB::transaction(function () use ($competency): void {
                $scoreIds = DB::table('scores')
                    ->where('competency_id', $competency->id)
                    ->pluck('id');

                DB::table('competency_gaps')
                    ->where('competency_id', $competency->id)
                    ->orWhereIn('supervisor_2_score_id', $scoreIds)
                    ->delete();

                DB::table('assessment_evidences')
                    ->where('competency_id', $competency->id)
                    ->delete();

                DB::table('assessment_indicator_results')
                    ->where('competency_id', $competency->id)
                    ->delete();

                DB::table('scores')
                    ->where('competency_id', $competency->id)
                    ->delete();

                DB::table('hr_expectations')
                    ->where('competency_id', $competency->id)
                    ->delete();

                $levelIds = $competency->levels()->pluck('id');

                DB::table('comp_level_indicators')
                    ->whereIn('competency_level_id', $levelIds)
                    ->delete();

                $competency->levels()->delete();
                $competency->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'competency' => 'ไม่สามารถลบสมรรถนะนี้ได้ กรุณาตรวจสอบข้อมูลที่เชื่อมอยู่แล้วลองอีกครั้ง',
            ]);
        }

        return back()->with('success', 'ลบสมรรถนะเรียบร้อยแล้ว');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $rows = $this->csvRows($request->file('file')->getRealPath());

        if ($rows === []) {
            return back()->withErrors(['file' => 'ไม่พบข้อมูลในไฟล์ CSV']);
        }

        $this->validateImportContinuity($rows);

        $existingCodesToSkip = $this->validateImportAgainstExistingData($rows);

        DB::transaction(function () use ($rows, $existingCodesToSkip): void {
            foreach ($this->groupImportRows($rows) as $code => $groupedRows) {
                if (in_array($code, $existingCodesToSkip, true)) {
                    continue;
                }

                $firstRow = $groupedRows[0];
                $type = CompetencyType::query()
                    ->where('code', $firstRow['type'])
                    ->firstOrFail();

                $competency = Competency::query()->create([
                    'competency_type_id' => $type->id,
                    'code' => $code,
                    'name' => $firstRow['name'],
                    'detail' => $firstRow['description'] ?? null,
                ]);

                $levels = [];

                foreach ($groupedRows as $row) {
                    $levelNumber = (int) $row['level'];
                    $levels[$levelNumber] ??= [
                        'level' => $levelNumber,
                        'description' => $row['level_description'] ?? null,
                        'indicators' => [],
                    ];

                    $levels[$levelNumber]['indicators'][] = [
                        'description' => $row['indicator'],
                        'weight' => $row['weight'] === null ? null : (float) $row['weight'],
                    ];
                }

                $this->replaceLevels($competency, array_values($levels));
            }
        });

        return back()->with('success', 'นำเข้าข้อมูลสมรรถนะเรียบร้อยแล้ว');
    }

    private function validatedData(Request $request, ?Competency $competency = null): array
    {
        return $request->validate([
            'competency_type_id' => ['required', 'integer', Rule::exists('competency_types', 'id')],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('competencies', 'code')->ignore($competency?->id),
            ],
            'name' => [
                'required',
                'string',
                Rule::unique('competencies', 'name')->ignore($competency?->id),
            ],
            'detail' => ['nullable', 'string'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*.level' => ['required', 'integer', 'min:1', 'max:5'],
            'levels.*.description' => ['nullable', 'string'],
            'levels.*.indicators' => ['required', 'array', 'min:1'],
            'levels.*.indicators.*.description' => ['required', 'string'],
            'levels.*.indicators.*.weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ]);
    }

    private function competencyData(array $data): array
    {
        return [
            'competency_type_id' => $data['competency_type_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'detail' => $data['detail'] ?? null,
        ];
    }

    private function replaceLevels(Competency $competency, array $levels): void
    {
        $competency->levels()->delete();

        foreach ($levels as $levelData) {
            $level = $competency->levels()->create([
                'level' => $levelData['level'],
                'description' => $levelData['description'] ?? null,
            ]);

            foreach ($levelData['indicators'] as $indicatorData) {
                $level->indicators()->create([
                    'description' => $indicatorData['description'],
                    'weight' => $indicatorData['weight'] ?? null,
                ]);
            }
        }
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = null;
        $rows = [];
        $dataRowNumber = 0;
        $errors = [];
        $fillDownValues = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            if ($headers === null) {
                $candidateHeaders = array_map(
                    fn (string $header): string => $this->normalizeCsvHeader($header),
                    $data,
                );

                if (! $this->isImportHeaderRow($candidateHeaders)) {
                    continue;
                }

                $headers = $candidateHeaders;
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : null;
            }

            if ($this->isBlankImportRow($row)) {
                continue;
            }

            if ($this->isImportInstructionRow($row)) {
                continue;
            }

            $row = $this->fillDownImportRow($row, $fillDownValues);

            $dataRowNumber++;

            try {
                $rows[] = $this->normalizeImportRow($row, $dataRowNumber);
            } catch (ValidationException $exception) {
                foreach ($exception->errors()['file'] ?? [] as $message) {
                    $errors[] = $message;
                }
            }
        }

        fclose($handle);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => array_slice($errors, 0, 10),
            ]);
        }

        return $rows;
    }

    private function validateImportContinuity(array $rows): void
    {
        $messages = [];

        foreach ($this->groupImportRows($rows) as $code => $groupedRows) {
            $name = $groupedRows[0]['name'] ?? '-';
            $levels = collect($groupedRows)
                ->pluck('level')
                ->map(fn ($level): int => (int) $level)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($levels !== []) {
                for ($expectedLevel = 1; $expectedLevel <= max($levels); $expectedLevel++) {
                    if (! in_array($expectedLevel, $levels, true)) {
                        $messages[] = sprintf(
                            'รหัส %s, สมรรถนะ "%s" ขาดระดับ %d กรุณากรอกระดับให้ต่อเนื่องก่อน import',
                            $code,
                            $name,
                            $expectedLevel,
                        );
                    }
                }
            }

            collect($groupedRows)
                ->groupBy(fn (array $row): int => (int) $row['level'])
                ->each(function ($levelRows, int $levelNumber) use (&$messages, $code, $name): void {
                    $orders = collect($levelRows)
                        ->pluck('indicator_order')
                        ->map(fn ($order): string => trim((string) $order))
                        ->filter(fn (string $order): bool => $order !== '')
                        ->map(fn (string $order): int => (int) $order)
                        ->filter(fn (int $order): bool => $order > 0)
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                    if ($orders === []) {
                        return;
                    }

                    for ($expectedOrder = 1; $expectedOrder <= max($orders); $expectedOrder++) {
                        if (! in_array($expectedOrder, $orders, true)) {
                            $messages[] = sprintf(
                                'รหัส %s, สมรรถนะ "%s", ระดับ %d ขาดข้อที่ %d กรุณากรอกข้อที่ให้ต่อเนื่องก่อน import',
                                $code,
                                $name,
                                $levelNumber,
                                $expectedOrder,
                            );
                        }
                    }
                });
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([
                'file' => array_slice($messages, 0, 10),
            ]);
        }
    }

    private function validateImportAgainstExistingData(array $rows): array
    {
        $groupedRows = $this->groupImportRows($rows);
        $fileCompetencies = collect($groupedRows)
            ->map(fn (array $rows, string $code): array => [
                'code' => $code,
                'name' => $rows[0]['name'] ?? '-',
            ])
            ->values();

        $existingCompetencies = Competency::query()
            ->with(['type', 'levels.indicators'])
            ->whereIn('code', array_keys($groupedRows))
            ->get()
            ->keyBy('code');

        $messages = [];

        foreach ($existingCompetencies as $code => $competency) {
            $fileName = $groupedRows[$code][0]['name'] ?? '-';

            if ($competency->name === $fileName) {
                $differenceLocation = $this->importDifferenceLocation($competency, $groupedRows[$code]);

                if ($differenceLocation !== null) {
                    $messages[] = sprintf(
                        'พบว่าสมรรถนะ "%s" (รหัส %s) มีข้อมูลในไฟล์ที่แตกต่างจากข้อมูลในระบบที่ %s ไม่สามารถ import ได้ โปรดเช็คความถูกต้อง',
                        $fileName,
                        $code,
                        $differenceLocation,
                    );
                }

                continue;
            }

            $messages[] = sprintf(
                'รหัสสมรรถนะ %s มีอยู่แล้วในระบบ (ชื่อเดิม "%s", ชื่อในไฟล์ "%s") กรุณาตรวจสอบข้อมูลสมรรถนะก่อนนำเข้า',
                $code,
                $competency->name,
                $fileName,
            );
        }

        $codesAlreadyReported = $existingCompetencies->keys()->all();
        $namesToCheck = $fileCompetencies
            ->reject(fn (array $row): bool => in_array($row['code'], $codesAlreadyReported, true))
            ->pluck('name')
            ->filter(fn (string $name): bool => $name !== '' && $name !== '-')
            ->unique()
            ->values();

        if ($namesToCheck->isNotEmpty()) {
            $existingNames = Competency::query()
                ->whereIn('name', $namesToCheck)
                ->get()
                ->keyBy('name');

            foreach ($fileCompetencies as $fileCompetency) {
                if (in_array($fileCompetency['code'], $codesAlreadyReported, true)) {
                    continue;
                }

                $existingCompetency = $existingNames->get($fileCompetency['name']);

                if ($existingCompetency === null) {
                    continue;
                }

                $messages[] = sprintf(
                    'ชื่อสมรรถนะ "%s" มีอยู่แล้วในระบบ (รหัสเดิม %s, รหัสในไฟล์ %s) กรุณาตรวจสอบข้อมูลสมรรถนะก่อนนำเข้า',
                    $fileCompetency['name'],
                    $existingCompetency->code,
                    $fileCompetency['code'],
                );
            }
        }

        $duplicateNamesInFile = $fileCompetencies
            ->groupBy('name')
            ->filter(fn ($items, string $name): bool => $name !== '' && $name !== '-' && $items->pluck('code')->unique()->count() > 1);

        foreach ($duplicateNamesInFile as $name => $items) {
            $messages[] = sprintf(
                'ชื่อสมรรถนะ "%s" ซ้ำกันในไฟล์ (รหัส %s) กรุณาให้ชื่อสมรรถนะไม่ซ้ำกันก่อน import',
                $name,
                $items->pluck('code')->unique()->implode(', '),
            );
        }

        if ($messages === []) {
            return $existingCompetencies
                ->filter(fn (Competency $competency, string $code): bool => ($groupedRows[$code][0]['name'] ?? null) === $competency->name)
                ->keys()
                ->all();
        }

        throw ValidationException::withMessages([
            'file' => array_slice($messages, 0, 10),
        ]);
    }

    private function importDifferenceLocation(Competency $competency, array $rows): ?string
    {
        $existing = $this->normalizeExistingCompetencyForImportComparison($competency);
        $incoming = $this->normalizeImportRowsForComparison($rows);

        foreach (['type', 'code', 'name', 'description'] as $field) {
            if ($existing[$field] !== $incoming[$field]) {
                return 'ข้อมูลหลัก';
            }
        }

        $levelNumbers = collect(array_keys($existing['levels']))
            ->merge(array_keys($incoming['levels']))
            ->unique()
            ->sort()
            ->values();

        foreach ($levelNumbers as $levelNumber) {
            $existingLevel = $existing['levels'][$levelNumber] ?? null;
            $incomingLevel = $incoming['levels'][$levelNumber] ?? null;

            if ($existingLevel === null || $incomingLevel === null) {
                return sprintf('ระดับ %s', $levelNumber);
            }

            if ($existingLevel['description'] !== $incomingLevel['description']) {
                return sprintf('ระดับ %s', $levelNumber);
            }

            $indicatorCount = max(count($existingLevel['indicators']), count($incomingLevel['indicators']));

            for ($index = 0; $index < $indicatorCount; $index++) {
                $existingIndicator = $existingLevel['indicators'][$index] ?? null;
                $incomingIndicator = $incomingLevel['indicators'][$index] ?? null;

                if ($existingIndicator !== $incomingIndicator) {
                    return sprintf('ระดับ %s ข้อที่ %d', $levelNumber, $index + 1);
                }
            }
        }

        return null;
    }

    private function normalizeExistingCompetencyForImportComparison(Competency $competency): array
    {
        return [
            'type' => $competency->type?->code ?? '',
            'code' => $competency->code,
            'name' => $competency->name,
            'description' => $this->normalizeComparableText($competency->detail),
            'levels' => $competency->levels
                ->sortBy('level')
                ->mapWithKeys(fn ($level): array => [
                    (int) $level->level => [
                        'description' => $this->normalizeComparableText($level->description),
                        'indicators' => $level->indicators
                            ->sortBy('id')
                            ->map(fn ($indicator): array => [
                                'description' => $this->normalizeComparableText($indicator->description),
                                'weight' => $this->normalizeComparableWeight($indicator->weight),
                            ])
                            ->values()
                            ->all(),
                    ],
                ])
                ->all(),
        ];
    }

    private function normalizeImportRowsForComparison(array $rows): array
    {
        $firstRow = $rows[0];
        $levels = [];

        foreach ($rows as $row) {
            $levelNumber = (int) $row['level'];
            $levels[$levelNumber] ??= [
                'description' => $this->normalizeComparableText($row['level_description'] ?? null),
                'indicators' => [],
            ];

            $levels[$levelNumber]['indicators'][] = [
                'description' => $this->normalizeComparableText($row['indicator']),
                'weight' => $this->normalizeComparableWeight($row['weight']),
            ];
        }

        ksort($levels);

        return [
            'type' => $firstRow['type'],
            'code' => $firstRow['code'],
            'name' => $firstRow['name'],
            'description' => $this->normalizeComparableText($firstRow['description'] ?? null),
            'levels' => $levels,
        ];
    }

    private function normalizeComparableText(?string $value): string
    {
        return trim((string) $value);
    }

    private function normalizeComparableWeight(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = strtolower(trim(str_replace('*', '', $header)));

        return match ($header) {
            'competency_type', 'competency type', 'type_code', 'ประเภท' => 'type',
            'รหัส' => 'code',
            'ชื่อสมรรถนะ' => 'name',
            'detail', 'คำอธิบาย' => 'description',
            'level_detail', 'level description' => 'level_description',
            'ระดับ' => 'level',
            'indicator_order', 'ข้อที่' => 'indicator_order',
            'indicator_description', 'behavior', 'behaviour', 'พฤติกรรมบ่งชี้' => 'indicator',
            'น้ำหนัก' => 'weight',
            default => str_replace([' ', '-'], '_', $header),
        };
    }

    private function normalizeImportRow(array $row, int $dataRowNumber): array
    {
        $type = strtoupper(trim((string) ($row['type'] ?? '')));
        $rawCode = trim((string) ($row['code'] ?? ''));
        $rawLevel = trim((string) ($row['level'] ?? ''));
        $levelDescription = trim((string) ($row['level_description'] ?? ''));
        if ($levelDescription === '' && ! preg_match('/^\d+$/', $rawLevel)) {
            $levelDescription = $rawLevel;
        }

        $normalized = [
            'type' => $type,
            'code' => $this->normalizeImportCode($type, $rawCode),
            'name' => trim((string) ($row['name'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')) ?: null,
            'level' => $this->normalizeImportLevel($rawLevel),
            'level_description' => $levelDescription ?: null,
            'indicator_order' => trim((string) ($row['indicator_order'] ?? '')),
            'indicator' => trim((string) ($row['indicator'] ?? '')),
            'weight' => trim((string) ($row['weight'] ?? '')) ?: null,
        ];

        $validator = validator($normalized, [
            'type' => ['required', 'string', Rule::exists('competency_types', 'code')],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'level' => ['required', 'integer', 'min:1', 'max:5'],
            'level_description' => ['nullable', 'string'],
            'indicator' => ['required', 'string'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'file' => $this->importRowErrorMessages($dataRowNumber, $normalized, $validator->errors()->messages()),
            ]);
        }

        return $normalized;
    }

    private function importRowErrorMessages(int $dataRowNumber, array $row, array $errors): array
    {
        $messages = [];
        $code = $row['code'] !== '' ? $row['code'] : '-';
        $name = $row['name'] !== '' ? $row['name'] : '-';
        $level = $row['level'] !== '' ? $row['level'] : '-';
        $indicatorOrder = trim((string) ($row['indicator_order'] ?? ''));
        $indicatorText = $indicatorOrder !== '' ? sprintf(', ข้อที่ %s', $indicatorOrder) : '';

        foreach ($errors as $field => $fieldErrors) {
            $messages[] = sprintf(
                '%s (รหัส %s, สมรรถนะ "%s", ระดับ %s%s)',
                $this->importFieldErrorText($field, $fieldErrors),
                $code,
                $name,
                $level,
                $indicatorText,
            );
        }

        return $messages;
    }

    private function importFieldErrorText(string $field, array $fieldErrors): string
    {
        $label = match ($field) {
            'type' => 'ประเภทสมรรถนะ',
            'code' => 'รหัส',
            'name' => 'ชื่อสมรรถนะ',
            'description' => 'คำอธิบาย',
            'level' => 'ระดับ',
            'level_description' => 'คำอธิบายระดับ',
            'indicator' => 'พฤติกรรมบ่งชี้',
            'weight' => 'น้ำหนัก',
            default => $field,
        };

        $text = implode(' ', $fieldErrors);

        if (str_contains($text, 'required')) {
            return sprintf('คอลัมน์ "%s" ห้ามว่าง', $label);
        }

        if ($field === 'type' && str_contains($text, 'selected')) {
            return sprintf('%s ในไฟล์ยังไม่มีในระบบ กรุณาเพิ่มประเภทนี้ก่อนนำเข้าข้อมูล', $label);
        }

        if ($field === 'level') {
            return sprintf('คอลัมน์ %s ต้องเป็นตัวเลขระดับ 1-5', $label);
        }

        if ($field === 'weight') {
            return sprintf('คอลัมน์ "%s" ต้องเป็นตัวเลข 0-999.99', $label);
        }

        return sprintf('คอลัมน์ "%s" ไม่ถูกต้อง', $label);
    }

    private function isImportHeaderRow(array $headers): bool
    {
        $requiredHeaders = ['type', 'code', 'name', 'level', 'indicator'];

        return count(array_intersect($requiredHeaders, $headers)) === count($requiredHeaders);
    }

    private function isImportInstructionRow(array $row): bool
    {
        $type = trim((string) ($row['type'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));

        return str_contains($type, '/') || str_contains($code, 'เช่น');
    }

    private function isBlankImportRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function fillDownImportRow(array $row, array &$fillDownValues): array
    {
        foreach (['type', 'code', 'name', 'description', 'level', 'level_description'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value === '' && isset($fillDownValues[$field])) {
                $row[$field] = $fillDownValues[$field];
                continue;
            }

            if ($value !== '') {
                $fillDownValues[$field] = $value;
            }
        }

        return $row;
    }

    private function normalizeImportCode(string $type, string $code): string
    {
        $code = strtoupper(trim($code));

        if ($type !== '' && preg_match('/^\d+$/', $code) === 1) {
            return sprintf('%s-%03d', $type, (int) $code);
        }

        return $code;
    }

    private function normalizeImportLevel(string $level): string
    {
        $level = trim($level);

        if (preg_match('/\d+/', $level, $matches) === 1) {
            return $matches[0];
        }

        return $level;
    }

    private function groupImportRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['code']][] = $row;
        }

        ksort($grouped);

        return $grouped;
    }
}
