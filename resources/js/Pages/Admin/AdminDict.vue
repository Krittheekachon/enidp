<script lang="tsx">
import { Fragment, computed, defineComponent, ref, watch, watchEffect, type PropType } from "vue";
import { router } from "@inertiajs/vue3";
const useState = (initial: any) => {
  const state = ref(typeof initial === "function" ? initial() : initial);
  const setState = (next: any) => {
    state.value = typeof next === "function" ? next(state.value) : next;
  };
  return [state, setState] as const;
};

const useEffect = (effect: any) => {
  watchEffect((onCleanup) => {
    const cleanup = effect();
    if (typeof cleanup === "function") onCleanup(cleanup);
  });
};

interface AdminDictProps {competencies: any[];setCompetencies: any;competencyTypes: any[];onDirtyChange?: (dirty: boolean) => void;}const AdminDict = defineComponent({ name: "AdminDict", props: ["competencies", "setCompetencies", "competencyTypes", "onDirtyChange"], setup(__props) {const { setCompetencies, competencyTypes, onDirtyChange } = __props as any;const competencyList = ref<any[]>([...((__props as any).competencies || [])]);watch(() => (__props as any).competencies, (next) => {competencyList.value = [...(next || [])];}, { deep: true });const [view, setView] = useState("list");const [isDirty, setIsDirty] = useState(false);const [status, setStatus] = useState<any>(null);const showStatus = (type: string, msg: string) => {setStatus({ type, msg });setTimeout(() => setStatus(null), 4000);};const [expandedCode, setExpandedCode] = useState<string | null>(null);
    const [expandedLevel, setExpandedLevel] = useState<number | null>(null);
    const [sortBy, setSortBy] = useState("newest");
    const [typeFilter, setTypeFilter] = useState("ทั้งหมด");
    const [searchTerm, setSearchTerm] = useState("");
    const [levelFilter, setLevelFilter] = useState("ทั้งหมด");
    const [completionFilter, setCompletionFilter] = useState("ทั้งหมด");
    const totalCompetencies = computed(() => competencyList.value.length);

    useEffect(() => {
      onDirtyChange?.(isDirty.value);

      const warnBeforeUnload = (event: BeforeUnloadEvent) => {
        if (!isDirty.value) return;
        event.preventDefault();
        event.returnValue = "";
      };

      window.addEventListener("beforeunload", warnBeforeUnload);
      return () => {
        window.removeEventListener("beforeunload", warnBeforeUnload);
      };
    }, [isDirty.value, onDirtyChange]);

    useEffect(() => () => onDirtyChange?.(false), [onDirtyChange]);

    const syncCompetenciesFromPage = (responsePage: any) => {
      if (Array.isArray(responsePage.props.competencies)) {
        competencyList.value = [...responsePage.props.competencies];
        setCompetencies(responsePage.props.competencies);
      }
    };

    const persistOptions = {
      preserveScroll: true,
      preserveState: true,
      onSuccess: syncCompetenciesFromPage,
      onError: (errors: any) => {
        const firstError = Object.values(errors || {})[0];
        showStatus("e", firstError || "ไม่สามารถบันทึกข้อมูลสมรรถนะได้");
      }
    };

    const deleteComp = (c: any) => {
      if (!c.id) return;
      if (!window.confirm(`ต้องการลบสมรรถนะ ${c.cd} ใช่หรือไม่`)) return;

      router.delete(route("admin.competencies.destroy", c.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (responsePage: any) => {
          const nextCompetencies = Array.isArray(responsePage.props.competencies)
            ? responsePage.props.competencies
            : competencyList.value.filter((item: any) => item.id !== c.id);

          if (nextCompetencies.some((item: any) => item.id === c.id)) {
            showStatus("e", "หลังบ้านตอบกลับแล้ว แต่ข้อมูลสมรรถนะยังไม่ถูกลบจากฐานข้อมูล");
            return;
          }

          competencyList.value = nextCompetencies;
          setCompetencies(nextCompetencies);
          showStatus("s", "ลบข้อมูลสมรรถนะเรียบร้อยแล้ว");
        },
        onError: (errors: any) => {
          const firstError = Object.values(errors || {})[0];
          showStatus("e", firstError || "ไม่สามารถลบข้อมูลสมรรถนะได้");
        }
      });
    };
    const getCompetencyTypeCode = (item: any) => typeof item === "string" ? item : item?.code || item?.name || "";
    const getCompetencyTypeLabel = (item: any) => {
      const code = getCompetencyTypeCode(item);
      const fullName = typeof item === "string" ? "" : item?.fullName || item?.label || "";
      return fullName ? `${code} - ${fullName}` : code;
    };

    const getCompType = (c: any) => {
      if (c.t === "FC") {
        if (c.cd.startsWith("FC1-")) return "FC1";
        if (c.cd.startsWith("FC2-")) return "FC2";
      }
      return c.t;
    };

    const getCompTag = (c: any) => {
      const t = getCompType(c);
      return `tag-${t.toLowerCase()}`;
    };

    const competencyTypeSummary = computed(() => {
      const counts = competencyList.value.reduce((summary: Record<string, number>, competency: any) => {
        const type = getCompType(competency) || "ไม่ระบุ";
        summary[type] = (summary[type] || 0) + 1;
        return summary;
      }, {});
      const configuredTypes = ((__props as any).competencyTypes || [])
        .map((type: any) => ({
          code: getCompetencyTypeCode(type),
          fullName: typeof type === "string" ? "" : type?.fullName || type?.label || "",
        }))
        .filter((type: any) => type.code);
      const configuredCodes = new Set(configuredTypes.map((type: any) => type.code));
      const unconfiguredTypes = Object.keys(counts)
        .filter((code) => !configuredCodes.has(code))
        .sort((typeA, typeB) => typeA.localeCompare(typeB))
        .map((code) => ({ code, fullName: "" }));

      return [...configuredTypes, ...unconfiguredTypes].map((type: any) => ({
        ...type,
        count: counts[type.code] || 0,
      }));
    });

    const levelNumbersFor = (c: any) => (c.levels || []).map((level: any) => Number(level.lvl)).filter(Boolean);
    const hasCompleteLevels = (c: any) => {
      const levelsByNumber = new Map((c.levels || []).map((level: any) => [Number(level.lvl), level]));
      return [1, 2, 3, 4, 5].every((levelNumber) => {
        const level = levelsByNumber.get(levelNumber) as any;
        return (level?.indicators || []).length > 0;
      });
    };
    const hasMissingLevels = (c: any) => !hasCompleteLevels(c);
    const matchesSearch = (c: any) => {
      const keyword = searchTerm.value.trim().toLowerCase();
      if (!keyword) return true;

      return `${c.cd || ""} ${c.n || ""} ${c.det || ""} ${getCompType(c) || ""}`.toLowerCase().includes(keyword);
    };
    const resetFilters = () => {
      setSearchTerm("");
      setTypeFilter("ทั้งหมด");
      setLevelFilter("ทั้งหมด");
      setCompletionFilter("ทั้งหมด");
      setSortBy("newest");
    };

    const sorted = computed(() => competencyList.value.filter((c) => {
      const type = getCompType(c);
      const matchesType = typeFilter.value === "ทั้งหมด" || type === typeFilter.value;
      const matchesLevel = levelFilter.value === "ทั้งหมด" || levelNumbersFor(c).includes(Number(levelFilter.value));
      const matchesCompletion =
        completionFilter.value === "ทั้งหมด" ||
        (completionFilter.value === "ครบ 1-5" && hasCompleteLevels(c)) ||
        (completionFilter.value === "ยังไม่ครบ" && hasMissingLevels(c)) ||
        (completionFilter.value === "ไม่มีระดับ" && !(c.levels || []).length);

      return matchesSearch(c) && matchesType && matchesLevel && matchesCompletion;
    }).sort((a, b) => {
      if (sortBy.value === "name") return a.n.localeCompare(b.n, 'th');
      if (sortBy.value === "code") return a.cd.localeCompare(b.cd);
      if (sortBy.value === "type") {
        const order: any = { CC: 1, MC: 2, FC1: 3, FC2: 4 };
        const typeA = getCompType(a);
        const typeB = getCompType(b);
        return (order[typeA] || 9) - (order[typeB] || 9);
      }
      return 0;
    }));

    // Form states for Add/Edit
    const [editId, setEditId] = useState<string | null>(null);
    const [type, setType] = useState("CC");
    const [codeTail, setCodeTail] = useState("");
    const [name, setName] = useState("");
    const [desc, setDesc] = useState("");

    const generateLevel = (l: string) => ({ lvl: l, items: ["", "", "", ""], weights: ["0.25", "0.25", "0.25", "0.25"] });
    const [levels, setLevels] = useState([generateLevel("1"), generateLevel("2"), generateLevel("3"), generateLevel("4"), generateLevel("5")]);

    const clearForm = () => {
      setType("CC");setCodeTail("");setName("");setDesc("");setEditId(null);
      setLevels([generateLevel("1"), generateLevel("2"), generateLevel("3"), generateLevel("4"), generateLevel("5")]);
      setIsDirty(false);
    };

    const closeForm = () => {
      if (isDirty.value && !window.confirm("มีข้อมูลสมรรถนะที่ยังไม่ได้บันทึก ต้องการออกจากหน้านี้หรือไม่")) return;
      clearForm();
      setView("list");
    };

    const editComp = (c: any) => {
      const parts = c.cd.split("-");
      setEditId(c.cd);
      setType(c.t || parts[0]);
      setCodeTail(parts.slice(1).join("-"));
      setName(c.n);
      setDesc(c.det);
      if (c.levels) {
        setLevels(c.levels.map((l: any) => ({
          lvl: String(l.lvl),
          items: l.indicators.length >= 4 ? l.indicators.slice(0, 4) : [...l.indicators, ...Array(4 - l.indicators.length).fill("")],
          weights: l.weights && l.weights.length >= 4 ? l.weights.map(String).slice(0, 4) : ["0.25", "0.25", "0.25", "0.25"]
        })));
      } else {
        setLevels([generateLevel("1"), generateLevel("2"), generateLevel("3"), generateLevel("4"), generateLevel("5")]);
      }
      setIsDirty(false);
      setView("edit");
    };

    const saveComp = () => {
      const code = `${type.value}-${codeTail.value}`;
      const selectedType = competencyTypes.find((t: any) => getCompetencyTypeCode(t) === type.value);
      const selectedTypeId = selectedType?.id || null;
      if (!selectedTypeId) {showStatus("e", "กรุณาเพิ่มประเภทสมรรถนะก่อน");return;}
      if (!codeTail.value.trim()) {showStatus("e", "กรุณากรอกรหัสสมรรถนะ");return;}
      if (!name.value.trim()) {showStatus("e", "กรุณากรอกชื่อสมรรถนะ");return;}
      if (!desc.value.trim()) {showStatus("e", "กรุณากรอกรายละเอียดสมรรถนะ");return;}

      for (let i = 0; i < levels.value.length; i++) {
        const lv = levels.value[i];
        for (let j = 0; j < lv.items.length; j++) {
          if (!lv.items[j].trim()) {
            showStatus("e", `กรุณากรอกพฤติกรรมบ่งชี้ให้ครบทุกข้อ (ระดับที่ ${lv.lvl} ข้อที่ ${j + 1} ยังว่างอยู่)`);
            return;
          }
        }
      }

      const alreadyExistsCode = competencyList.value.some((c) => c.cd === code && c.cd !== editId.value);
      const alreadyExistsName = competencyList.value.some((c) => c.n === name.value && c.cd !== editId.value);
      if (alreadyExistsCode) {showStatus("e", `รหัสสมรรถนะ ${code} มีอยู่ในระบบแล้ว กรุณาใช้รหัสอื่น`);return;}
      if (alreadyExistsName) {showStatus("e", `ชื่อสมรรถนะ "${name.value}" มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น`);return;}

      const payload = {
        competency_type_id: selectedTypeId,
        code,
        name: name.value.trim(),
        detail: desc.value.trim(),
        levels: levels.value.map((l) => ({
          level: parseInt(l.lvl),
          description: "",
          indicators: l.items.filter((it) => it.trim()).map((indicator, index) => ({
            description: indicator.trim(),
            weight: parseFloat(l.weights[index]) || 0
          }))
        }))
      };

      if (editId.value) {
        const current = competencyList.value.find((c) => c.cd === editId.value);
        if (!current?.id) {showStatus("e", "ไม่พบข้อมูลสมรรถนะที่ต้องการแก้ไข");return;}
        router.put(route("admin.competencies.update", current.id), payload, {
          ...persistOptions,
          onSuccess: (responsePage: any) => {
            syncCompetenciesFromPage(responsePage);
            showStatus("s", "แก้ไขข้อมูลสมรรถนะเรียบร้อยแล้ว");
            clearForm();
            setView("list");
          }
        });
      } else {
        router.post(route("admin.competencies.store"), payload, {
          ...persistOptions,
          onSuccess: (responsePage: any) => {
            syncCompetenciesFromPage(responsePage);
            showStatus("s", "เพิ่มสมรรถนะใหม่เรียบร้อยแล้ว");
            clearForm();
            setView("list");
          }
        });
      }
    };


    const removeLevelRow = (idx: number) => {
      setLevels(levels.value.filter((_, i) => i !== idx));
      setIsDirty(true);
    };

    const updateIndicator = (lvIdx: number, itemIdx: number, val: string) => {
      const next = [...levels.value];
      const nextItems = [...next[lvIdx].items];
      nextItems[itemIdx] = val;
      next[lvIdx] = { ...next[lvIdx], items: nextItems };
      setLevels(next);
      setIsDirty(true);
    };

    const updateWeight = (lvIdx: number, itemIdx: number, val: string) => {
      const next = [...levels.value];
      const nextWeights = [...next[lvIdx].weights];
      nextWeights[itemIdx] = val;
      next[lvIdx] = { ...next[lvIdx], weights: nextWeights };
      setLevels(next);
      setIsDirty(true);
    };

    const updateLvlNum = (idx: number, val: string) => {
      const next = [...levels.value];
      next[idx] = { ...next[idx], lvl: val };
      setLevels(next);
      setIsDirty(true);
    };

    const LEVEL_LABELS = ["ระดับที่ 1 (Basic Level)", "ระดับที่ 2 (Doing Level)", "ระดับที่ 3 (Developing Level)", "ระดับที่ 4 (Advanced Level)", "ระดับที่ 5 (Expert Level)"];return () =>


    <>
      <div class="flex ic jb mb20">
        <div>
          <div class="sec-t">{view.value === 'list' ? 'พจนานุกรมสมรรถนะ ' : view.value === 'add' ? 'เพิ่มสมรรถนะการประเมิน ' : 'แก้ไขสมรรถนะการประเมิน '}</div>
          <div class="sec-s">{view.value === 'list' ? 'กำหนดสมรรถนะรายข้อ ' : 'กรอกข้อมูลสมรรถนะและรายละเอียดระดับพฤติกรรม'}</div>
        </div>
        <div class="flex" style={{ gap: "8px" }}>
          {view.value === 'list' ?
          <button class="btn btn-p" onClick={() => {clearForm();setType(getCompetencyTypeCode(competencyTypes[0]) || "");setView("add");}}>+ เพิ่มสมรรถนะ</button> :

          <button class="btn btn-s" onClick={closeForm}>⬅ กลับหน้ารายการ</button>
          }
        </div>
      </div>

      {status.value &&
      <div class="status-msg anim-fade-in" style={{ position: "fixed", top: "20px", right: "20px", zIndex: 9999, padding: "12px 24px", borderRadius: "var(--r)", boxShadow: "0 10px 15px -3px rgba(0, 0, 0, 0.2)", background: status.value.type === 's' ? '#10b981' : '#ef4444', color: '#fff', fontWeight: 700, display: "flex", alignItems: "center", gap: "10px" }}>
          <span>{status.value.type === 's' ? '' : ''}</span>
          {status.value.msg}
        </div>
      }

      {view.value !== 'list' ?
      <div class="anim-fade-in">
          <div class="card shadow-sm mb20">
            <div class="ch"><div class="ct">ข้อมูลสมรรถนะหลัก</div></div>
            <div class="cb">
              <div class="dict-main-fields mb16">
                <div class="fg dict-type-field">
                  <label class="lbl">1. ประเภทสมรรถนะ (Competency Type)</label>
                  <select class="sel" value={type.value} onChange={(e) => {setType(e.target.value);setIsDirty(true);}}>
                    {competencyTypes.map((t) => <option key={getCompetencyTypeCode(t)} value={getCompetencyTypeCode(t)}>{getCompetencyTypeLabel(t)}</option>)}
                  </select>
                </div>
                <div class="fg flex-1">
                  <label class="lbl">2. รหัสและชื่อสมรรถนะ (Code & Name)</label>
                  <div class="dict-code-name-fields">
                    <div class="dict-code-box">
                      <div class="static-code">{type.value}-</div>
                      <input class="inp dict-code-input" placeholder="001" value={codeTail.value} onChange={(e) => {setCodeTail(e.target.value);setIsDirty(true);}} />
                    </div>
                    <input class="inp dict-name-input" placeholder="กรอกชื่อสมรรถนะ..." value={name.value} onChange={(e) => {setName(e.target.value);setIsDirty(true);}} />
                  </div>
                </div>
              </div>
              <div class="fg">
                <label class="lbl">3. รายละเอียดสมรรถนะ (Description)</label>
                <textarea class="inp" rows={3} placeholder="กรอกคำอธิบายความหมายของสมรรถนะ..." value={desc.value} onChange={(e) => {setDesc(e.target.value);setIsDirty(true);}}></textarea>
              </div>
            </div>
          </div>

          <div class="card shadow-sm mb20">
            <div class="ch flex ic jb">
              <div class="ct">รายละเอียดพฤติกรรมบ่งชี้</div>
            </div>
            <div class="cb p0">
              <table class="tbl tbl-clean tbl-add-dict">
                <thead>
                  <tr>
                    <th style={{ width: '80px', textAlign: 'center' }}>ระดับ</th>
                    <th style={{ width: '100px', textAlign: 'center' }}>น้ำหนัก</th>
                    <th>รายละเอียดพฤติกรรม</th>
                    <th style={{ width: '60px' }}></th>
                  </tr>
                </thead>
                <tbody>
                  {levels.value.map((lv, lvIdx) =>
                <Fragment key={lvIdx}>
                      {lv.items.map((item, itemIdx) =>
                  <tr key={`${lvIdx}-${itemIdx}`} style={{ borderBottom: itemIdx === 3 ? '2.5px solid #e2e8f0' : '1px solid #f1f5f9' }}>
                          {itemIdx === 0 &&
                    <td rowSpan={4} style={{ verticalAlign: 'top', background: '#fcfdfe', borderRight: '1px solid #e2e8f0' }}>
                              <div class="flex col ic g8" style={{ marginTop: '4px' }}>
                                <div class="muted fs10 fw7">LEVEL</div>
                                <input class="inp ac" style={{ width: '50px' }} value={lv.lvl} onChange={(e) => updateLvlNum(lvIdx, e.target.value)} />
                                <button class="btn-icon-r" title="ลบระดับนี้" onClick={() => removeLevelRow(lvIdx)}></button>
                              </div>
                            </td>
                    }
                          <td style={{ verticalAlign: 'middle', textAlign: 'center' }}>
                            <div class="fw5 fs11 muted mb4">น้ำหนัก</div>
                            <input class="inp ac" style={{ width: '80px', color: 'var(--blue)', fontWeight: 800 }} type="text" value={lv.weights ? lv.weights[itemIdx] : 0.25} onChange={(e) => updateWeight(lvIdx, itemIdx, e.target.value)} />
                          </td>
                          <td>
                            <input class="inp" placeholder={`พฤติกรรมบ่งชี้ข้อที่ ${itemIdx + 1}...`} value={item} onChange={(e) => updateIndicator(lvIdx, itemIdx, e.target.value)} />
                          </td>
                        </tr>
                  )}
                    </Fragment>
                )}
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex jc g12 mt24 mb40">
            <button class="btn btn-s w140" onClick={() => {clearForm();setView("list");}}>ยกเลิก</button>
            <button class="btn btn-p w140" onClick={saveComp}>บันทึกข้อมูล</button>
          </div>
        </div> :

      <>
          <div class="dict-summary-grid">
            <div class="dict-summary-card">
              <div class="dict-summary-label">สมรรถนะทั้งหมด</div>
              <div class="dict-summary-value">{totalCompetencies.value}</div>
              <div class="dict-summary-sub">รายการในพจนานุกรมสมรรถนะ</div>
            </div>
            {competencyTypeSummary.value.map((type) =>
              <div class={`dict-summary-card ${type.count === 0 ? 'is-empty' : ''}`} key={type.code}>
                <div class="dict-summary-label">ประเภท {type.code}</div>
                <div class="dict-summary-value">{type.count}</div>
                <div class="dict-summary-sub">{type.fullName || (type.count === 0 ? 'ยังไม่มีสมรรถนะ' : 'สมรรถนะ')}</div>
              </div>
            )}
          </div>

          <div class="dict-filter-panel">
            <div class="dict-search-box">
              <label class="dict-filter-label">ค้นหา</label>
              <input
                class="inp dict-search-input"
                value={searchTerm.value}
                onInput={(e) => setSearchTerm((e.target as HTMLInputElement).value)}
                placeholder="ค้นหารหัส ชื่อ ประเภท หรือคำอธิบาย"
              />
            </div>
            <div class="dict-filter-field">
              <label class="dict-filter-label">ประเภท</label>
              <select class="sel" value={typeFilter.value} onChange={(e) => setTypeFilter(e.target.value)}>
                <option value="ทั้งหมด">ทั้งหมด</option>
                {competencyTypes.map((t) => <option key={getCompetencyTypeCode(t)} value={getCompetencyTypeCode(t)}>{getCompetencyTypeLabel(t)}</option>)}
              </select>
            </div>
            <div class="dict-filter-field">
              <label class="dict-filter-label">มี Level</label>
              <select class="sel" value={levelFilter.value} onChange={(e) => setLevelFilter(e.target.value)}>
                <option value="ทั้งหมด">ทุกระดับ</option>
                {[1, 2, 3, 4, 5].map((levelNumber) =>
                  <option key={levelNumber} value={String(levelNumber)}>Level {levelNumber}</option>
                )}
              </select>
            </div>
            <div class="dict-filter-field">
              <label class="dict-filter-label">สถานะระดับ</label>
              <select class="sel" value={completionFilter.value} onChange={(e) => setCompletionFilter(e.target.value)}>
                <option value="ทั้งหมด">ทั้งหมด</option>
                <option value="ครบ 1-5">ครบ Level 1-5</option>
                <option value="ยังไม่ครบ">ยังไม่ครบ</option>
                <option value="ไม่มีระดับ">ไม่มีข้อมูลระดับ</option>
              </select>
            </div>
            <div class="dict-filter-field">
              <label class="dict-filter-label">เรียงตาม</label>
              <select class="sel" value={sortBy.value} onChange={(e) => setSortBy(e.target.value)}>
                <option value="newest">ค่าเริ่มต้น</option>
                <option value="code">รหัสสมรรถนะ</option>
                <option value="name">ชื่อสมรรถนะ</option>
                <option value="type">ประเภทสมรรถนะ</option>
              </select>
            </div>
            <div class="dict-filter-summary">
              <span>{sorted.value.length}/{totalCompetencies.value} รายการ</span>
              <button class="btn btn-s btn-sm" type="button" onClick={resetFilters}>ล้าง filter</button>
            </div>
          </div>

          <div class="dict-list">
            {sorted.value.map((c, i) => {
            const isExpanded = expandedCode.value === c.cd;
            return (
              <div key={i} class="dict-group">
                  <div
                  class={`dict-row ${isExpanded ? 'active' : ''}`}
                  onClick={() => {
                    setExpandedCode(isExpanded ? null : c.cd);
                    setExpandedLevel(null);
                  }}>
                  
                    <div class="dict-info">
                      <span class="dict-code">{c.cd}</span>
                      <span class="dict-name">{c.n}</span>
                      <span class={`dict-tag ${getCompTag(c)}`}>{getCompType(c)}</span>
                    </div>
                    <div class="dict-dots"></div>
                    <div class="flex ic g8">
                      <button class="btn btn-xs btn-s" onClick={(e) => {e.stopPropagation();editComp(c);}}>แก้ไข</button>
                      <button class="btn btn-xs btn-s" style={{ color: "#ef4444" }} onClick={(e) => {e.stopPropagation();deleteComp(c);}}>ลบ</button>
                      <div class="dict-arrow" style={{ transform: isExpanded ? 'rotate(90deg)' : 'none' }}>›</div>
                    </div>
                  </div>

                  {isExpanded &&
                <div class="dict-content anim-slide-down">
                      <div class="dict-desc">{c.det}</div>
                      <div class="dict-levels-list">
                        {[1, 2, 3, 4, 5].map((lvNum) => {
                      const isLevelExpanded = expandedLevel.value === lvNum;
                      const lvData = c.levels?.find((l: any) => l.lvl === lvNum);
                      const indicators = lvData?.indicators || [];


                      return (
                        <div key={lvNum} class="dict-level-box">
                              <div
                            class={`dict-row-lvl ${isLevelExpanded ? 'active' : ''}`}
                            onClick={() => setExpandedLevel(isLevelExpanded ? null : lvNum)}>
                            
                                <span class="dict-lvl-label">{LEVEL_LABELS[lvNum - 1]}</span>
                                <div class="dict-dots"></div>
                                <div class="dict-arrow" style={{ transform: isLevelExpanded ? 'rotate(90deg)' : 'none' }}>›</div>
                              </div>
                              {isLevelExpanded &&
                          <div class="dict-indicators anim-fade-in">
                                  {indicators.map((ind: string, idx: number) =>
                            <div key={idx} class="dict-ind-row">
                                      <div class="dict-weight">
                                        <div class="fs10 muted fw4">น้ำหนัก</div>
                                        {lvData?.weights?.[idx] ?? 0.25}
                                      </div>
                                      <div class="dict-ind-text">
                                        {ind.startsWith("▪") ? ind : `▪ ${ind}`}
                                      </div>
                                    </div>
                            )}
                                  {indicators.length === 0 && <div class="muted fs12 py8">ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้</div>}
                                </div>
                          }
                            </div>);

                    })}
                      </div>
                    </div>
                }
                </div>);

          })}
          </div>
          {sorted.value.length === 0 && <div class="dict-empty">ยังไม่มีข้อมูลสมรรถนะในพจนานุกรม</div>}
        </>
      }

      <style>{`
        .dict-summary-grid { display: grid; grid-auto-flow: column; grid-auto-columns: calc((100% - 36px) / 4); gap: 12px; margin-bottom: 16px; padding-bottom: 8px; overflow-x: auto; overscroll-behavior-inline: contain; scroll-snap-type: inline mandatory; scrollbar-width: thin; scrollbar-color: #94a3b8 transparent; }
        .dict-summary-grid::-webkit-scrollbar { height: 6px; }
        .dict-summary-grid::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 999px; }
        .dict-summary-card { min-width: 0; background: #fff; border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px 20px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); scroll-snap-align: start; }
        .dict-summary-card.is-empty { background: #f8fafc; box-shadow: none; }
        .dict-summary-card.is-empty .dict-summary-value { color: var(--text3); }
        .dict-summary-label { font-size: 12px; font-weight: 800; color: var(--text3); }
        .dict-summary-value { margin-top: 6px; font-size: 34px; line-height: 1; font-weight: 900; color: var(--navy); }
        .dict-summary-sub { margin-top: 8px; font-size: 12px; font-weight: 700; color: var(--text3); }
        .dict-filter-panel { display: grid; grid-template-columns: minmax(260px, 1.7fr) minmax(170px, 0.9fr) minmax(150px, 0.75fr) minmax(170px, 0.9fr) minmax(160px, 0.8fr) auto; gap: 10px; align-items: end; margin-bottom: 16px; padding: 14px; border: 1px solid var(--border); border-radius: var(--r-lg); background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); }
        .dict-filter-label { display: block; margin-bottom: 5px; color: var(--text3); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .dict-search-box, .dict-filter-field { min-width: 0; }
        .dict-search-input { min-height: 38px; }
        .dict-filter-summary { display: flex; align-items: center; justify-content: flex-end; gap: 8px; color: var(--text3); font-size: 12px; font-weight: 800; white-space: nowrap; }
        .dict-list { display: flex; flex-direction: column; gap: 4px; background: #fff; border-radius: var(--r-lg); border: 1px solid var(--border); overflow: hidden; }
        .dict-empty { display: flex; align-items: center; justify-content: center; min-height: 180px; margin-top: -1px; border: 1px dashed var(--border); border-radius: var(--r-lg); background: #fff; color: var(--text3); font-size: 14px; font-weight: 700; }
        .dict-group { border-bottom: 1px solid #f1f5f9; }
        .dict-group:last-child { border-bottom: none; }
        
        .dict-row { display: flex; align-items: center; padding: 16px 20px; cursor: pointer; transition: 0.2s; }
        .dict-row:hover { background: #f8fafc; }
        .dict-row.active { background: #f8fafc; }
        
        .dict-info { display: flex; align-items: center; gap: 12px; }
        .dict-code { font-family: monospace; font-size: 11px; color: var(--text3); font-weight: 700; width: 60px; }
        .dict-name { font-weight: 700; font-size: 15px; color: var(--navy); }
        .dict-tag { font-size: 9px; padding: 1px 6px; border-radius: 4px; font-weight: 800; }
        
        .dict-dots { flex: 1; border-bottom: 1px dotted #cbd5e1; margin: 0 16px; align-self: center; margin-bottom: -4px; height: 12px; }
        .dict-arrow { color: var(--text3); font-size: 20px; font-weight: 300; width: 20px; text-align: center; }
        
        .dict-content { background: #fff; }
        .dict-desc { padding: 4px 20px 16px 92px; font-size: 12px; color: var(--text3); }
        
        .dict-levels-list { margin-left: 72px; padding-bottom: 16px; border-left: 2px solid #f1f5f9; }
        .dict-level-box { margin-bottom: 2px; }
        
        .dict-row-lvl { display: flex; align-items: center; padding: 10px 20px; cursor: pointer; transition: 0.2s; }
        .dict-row-lvl:hover { background: var(--blue-lt); }
        .dict-row-lvl.active { background: var(--blue-lt); color: var(--blue); }
        .dict-lvl-label { font-size: 13px; font-weight: 700; }
        
        .dict-indicators { padding: 8px 20px 16px 40px; }
        .dict-ind-row { display: flex; gap: 16px; padding: 8px 0; border-bottom: 1px solid #f8fafc; }
        .dict-weight { min-width: 40px; font-weight: 800; font-size: 13px; color: var(--blue); text-align: center; }
        .dict-ind-text { font-size: 13px; color: var(--text2); line-height: 1.6; }
        
        .anim-slide-down { animation: slideDown 0.3s ease-out; overflow: hidden; }
        @keyframes slideDown { from { max-height: 0; opacity: 0; } to { max-height: 2000px; opacity: 1; } }
        .anim-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        .dict-main-fields { display: grid; grid-template-columns: minmax(280px, 360px) minmax(0, 1fr); gap: 28px; align-items: end; }
        .dict-type-field { min-width: 0; }
        .dict-code-name-fields { display: grid; grid-template-columns: 230px minmax(260px, 1fr); gap: 12px; align-items: stretch; }
        .dict-code-box { display: flex; min-width: 0; overflow: hidden; border: 1px solid var(--border); border-radius: 8px; background: #fff; box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.08); }
        .static-code { display: inline-flex; align-items: center; flex: 0 0 auto; padding: 0 14px; background: #f8fafc; border-right: 1px solid var(--border); color: var(--text3); font-weight: 800; font-size: 14px; }
        .dict-code-input { width: 100%; min-width: 0; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; }
        .dict-name-input { min-width: 0; border-radius: 8px !important; }
        
        .tbl-add-dict th { background: #f8fafc; font-size: 12px; font-weight: 700; color: var(--text3); border-bottom: 1.5px solid var(--border); padding: 12px; }
        .tbl-add-dict td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .btn-icon-r { background: none; border: none; font-size: 16px; cursor: pointer; opacity: 0.6; transition: 0.2s; padding: 8px; border-radius: 6px; }
        .btn-icon-r:hover { opacity: 1; background: #fee2e2; }
        .w140 { width: 140px; }
        .ac { text-align: center; }
        .py8 { padding-top: 8px; padding-bottom: 8px; }
        .mr12 { margin-right: 12px; }
        .sel-sm { font-size: 12px; border-radius: 6px; }
        @media (max-width: 1100px) {
          .dict-filter-panel { grid-template-columns: repeat(2, minmax(0, 1fr)); }
          .dict-search-box, .dict-filter-summary { grid-column: 1 / -1; }
          .dict-filter-summary { justify-content: space-between; }
        }
        @media (max-width: 960px) {
          .dict-summary-grid { grid-auto-columns: minmax(240px, 82vw); }
          .dict-main-fields { grid-template-columns: 1fr; gap: 16px; }
          .dict-code-name-fields { grid-template-columns: 1fr; }
          .dict-code-box { max-width: 260px; }
        }
        @media (max-width: 640px) {
          .dict-filter-panel { grid-template-columns: 1fr; }
          .dict-filter-summary { align-items: stretch; flex-direction: column; }
        }
      `}</style>
    </>;} });



export default AdminDict;
</script>
