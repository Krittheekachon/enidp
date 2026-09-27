<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>Mock SSO Login</title>
  <style>
    body { font-family: sans-serif; max-width: 880px; margin: 56px auto; padding: 20px; background: #f8fafc; }
    h2 { color: #1e293b; margin-bottom: 6px; }
    .subtitle { color: #64748b; font-size: 13px; margin-bottom: 24px; }
    .user-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin: 10px 0; transition: 0.15s; background: #fff; }
    .user-card:hover { border-color: #cbd5e1; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06); }
    .user-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
    .name { font-weight: 700; font-size: 15px; color: #0f172a; }
    .meta { color: #64748b; font-size: 12px; margin-top: 4px; }
    .badge { display: inline-block; margin-top: 6px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #dbeafe; color: #1d4ed8; }
    .login-button { border: 0; border-radius: 6px; padding: 8px 12px; background: #1d4ed8; color: #fff; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .mail-test { margin-top: 14px; padding-top: 12px; border-top: 1px solid #e2e8f0; }
    .mail-title { color: #334155; font-size: 12px; font-weight: 800; margin-bottom: 8px; }
    .mail-grid { display: flex; flex-wrap: wrap; gap: 8px; }
    .mail-grid form { margin: 0; }
    .mail-button { border: 1px solid #bbf7d0; border-radius: 6px; padding: 7px 9px; background: #f0fdf4; color: #15803d; font-size: 12px; font-weight: 800; cursor: pointer; }
    .mail-button:hover { background: #dcfce7; }
    .danger-button { border: 1px solid #fecaca; border-radius: 6px; padding: 7px 9px; background: #fef2f2; color: #b91c1c; font-size: 12px; font-weight: 800; cursor: pointer; }
    .danger-button:hover { background: #fee2e2; }
    .notification-toggle { position: fixed; right: 20px; bottom: 20px; z-index: 20; border: 0; border-radius: 999px; padding: 10px 14px; color: #fff; font-size: 12px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18); }
    .notification-toggle.on { background: #16a34a; }
    .notification-toggle.off { background: #dc2626; }
    .notice { margin-bottom: 16px; padding: 10px 12px; border: 1px solid #bbf7d0; border-radius: 6px; background: #f0fdf4; color: #15803d; font-size: 13px; font-weight: 700; }
    .error { margin-bottom: 16px; padding: 10px 12px; border: 1px solid #fecaca; border-radius: 6px; background: #fef2f2; color: #b91c1c; font-size: 13px; font-weight: 700; }
  </style>
</head>
<body>
  <h2>Mock SSO Login</h2>
  <p class="subtitle">Dev mode - เลือก user เพื่อ login หรือกดส่งอีเมลทดสอบตามบทบาทของ user คนนั้น</p>

  @if($errors->any())
    <div class="error">{{ $errors->first() }}</div>
  @endif

  @if(session('mail_status'))
    <div class="notice">{{ session('mail_status') }}</div>
  @endif

  @php
    $mailTestsByRole = [
        'admin' => [
            'admin_new_user' => 'ผู้ใช้ใหม่',
            'admin_incomplete_user' => 'รอตรวจสอบข้อมูล',
            'admin_missing_expectation' => 'ยังไม่ตั้งค่าคาดหวัง',
        ],
        'hr' => [
            'hr_unmapped_competency' => 'กลุ่มงานไม่ผูกสมรรถนะ',
            'hr_remind_pending' => 'ปุ่มเตือนประเมิน',
            'reminder_assess' => 'รับเตือนประเมิน',
        ],
        'employee' => [
            'reminder_assess' => 'รับเตือนประเมิน',
            'status_pending' => 'รออนุมัติ',
            'status_revision' => 'ส่งกลับแก้ไข',
            'status_approved' => 'อนุมัติ เริ่ม IDP',
        ],
        'supervisor' => [
            'reminder_assess' => 'รับเตือนประเมิน',
            'assessment_submitted' => 'มีผลรอตรวจสอบ',
        ],
        'dept_head' => [
            'reminder_assess' => 'รับเตือนประเมิน',
            'assessment_submitted' => 'มีผลรอตรวจสอบ',
        ],
        'dean' => [
            'assessment_submitted' => 'มีผลรอตรวจสอบ',
        ],
    ];
  @endphp

  @forelse($mockUsers as $user)
    @php
      $mailTests = $mailTestsByRole[$user['role']] ?? ['reminder_assess' => 'รับเตือนประเมิน'];
    @endphp

    <div class="user-card">
      <div class="user-head">
        <div>
          <div class="name">{{ $user['name'] }}</div>
          <div class="meta">{{ $user['workline'] }} · {{ $user['division'] }} · {{ $user['position'] }}</div>
          <span class="badge">{{ $user['role'] }}</span>
        </div>
        <form method="POST" action="{{ route('mock.sso.login') }}">
          @csrf
          <input type="hidden" name="user_id" value="{{ $user['db_id'] }}">
          <button class="login-button" type="submit">Login</button>
        </form>
      </div>

      <div class="mail-test">
        <div class="mail-title">ทดสอบแจ้งเตือน reviewer 1 ไปที่ krittheekachon.s@kkumail.com · reviewer 2/3 ไปที่ chin172755@gmail.com</div>
        <div class="mail-grid">
          @foreach($mailTests as $type => $label)
            <form method="POST" action="{{ route('mock.sso.test-notification') }}">
              @csrf
              <input type="hidden" name="user_id" value="{{ $user['db_id'] }}">
              <input type="hidden" name="type" value="{{ $type }}">
              <button class="mail-button" type="submit">{{ $label }}</button>
            </form>
          @endforeach
          @if($user['db_id'] === 36)
            <form method="POST" action="{{ route('mock.sso.reset-assessment-flow') }}" onsubmit="return confirm('ล้างข้อมูลประเมินของนายซีอิ๊วขาว เด็กสมบูรณ์ ใช่ไหม');">
              @csrf
              <input type="hidden" name="user_id" value="{{ $user['db_id'] }}">
              <button class="danger-button" type="submit">ล้าง flow การประเมิน</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  @empty
    <p style="color:#4B5563;">ยังไม่มี User ในระบบ - กรุณา Import ข้อมูลก่อน</p>
  @endforelse
  @php($notificationsEnabled = \Illuminate\Support\Facades\Cache::get('dev_notifications_enabled', true))
  <button
    id="notification-toggle"
    class="notification-toggle {{ $notificationsEnabled ? 'on' : 'off' }}"
    type="button"
    data-enabled="{{ $notificationsEnabled ? '1' : '0' }}"
  >
    Notifications: {{ $notificationsEnabled ? 'ON' : 'OFF' }}
  </button>

  <script>
    (() => {
      const button = document.getElementById('notification-toggle');
      if (!button) return;

      const render = (enabled) => {
        button.dataset.enabled = enabled ? '1' : '0';
        button.classList.toggle('on', enabled);
        button.classList.toggle('off', !enabled);
        button.textContent = `Notifications: ${enabled ? 'ON' : 'OFF'}`;
      };

      button.addEventListener('click', async () => {
        button.disabled = true;

        try {
          const response = await fetch('{{ route('mock.sso.notification-toggle') }}', {
            headers: { Accept: 'application/json' },
          });
          const data = await response.json();
          render(Boolean(data.enabled));
        } finally {
          button.disabled = false;
        }
      });
    })();
  </script>
</body>
</html>
