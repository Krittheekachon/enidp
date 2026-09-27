export const ROLES_CONFIG = {
  admin: { lbl: "ผู้ดูแลระบบ", name: "วิชัย ระบบดี", av: "ว", pos: "ผู้ดูแลระบบคณะ", col: "#B93620" },
  hr: { lbl: "งานทรัพยากรบุคคล", name: "คุณมาลี ดีเสมอ", av: "ม", pos: "งานทรัพยากรบุคคล", col: "#C7432B" },
  employee: { lbl: "บุคลากร", name: "สมชาย มีสุข", av: "ส", pos: "นักวิชาการศึกษา · สายสนับสนุน", col: "#D84A2F" },
  dept_head: { lbl: "หัวหน้างาน", name: "กัญญารัตน์ ศรีวิชา", av: "ก", pos: "หัวหน้าหน่วยสนับสนุนการศึกษาและวิชาการ", col: "#B93620" },
  division_head: { lbl: "หัวหน้าฝ่าย", name: "หัวหน้าฝ่าย", av: "ฝ", pos: "หัวหน้าฝ่าย", col: "#B45309" },
  academic_department_head: { lbl: "หัวหน้าภาควิชา", name: "หัวหน้าภาควิชา", av: "ภ", pos: "หัวหน้าภาควิชา", col: "#0E7490" },
  supervisor: { lbl: "หัวหน้าหน่วย", name: "ผศ.ดร.ธนพล ไชยรักษ์", av: "ธ", pos: "รองคณบดีฝ่ายบริหาร", col: "#C7432B" },
  manager_dept: { lbl: "หัวหน้างาน", name: "กัญญารัตน์ ศรีวิชา", av: "ก", pos: "หัวหน้าหน่วยสนับสนุนการศึกษาและวิชาการ", col: "#B93620" },
  manager: { lbl: "ผู้บริหารคณะ", name: "รศ.ดร.กิตติพงศ์ แสงทอง", av: "ก", pos: "คณบดี", col: "#A93420" }
};

export const NAV_CONFIG = {
  employee: [
    {
      sec: "การประเมินของฉัน",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผลการประเมิน" },
        { id: "emp-idp", ic: "", lb: "แผนพัฒนา IDP" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    }
  ],
  supervisor: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผลการประเมิน" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "จัดการทีม",
      items: [
        { id: "dh-assess", ic: "", lb: "อนุมัติการประเมิน" },
        { id: "sup-gap", ic: "", lb: "ผลการประเมินของทีม" },
        { id: "dh-idp", ic: "", lb: "IDP & ติดตามทีม" }
      ]
    }
  ],
  dept_head: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผลการประเมิน" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "จัดการทีม",
      items: [
        { id: "sup-gap", ic: "", lb: "ผลการประเมินของทีม" },
        { id: "dh-idp", ic: "", lb: "IDP & ติดตามทีม" }
      ]
    }
  ],
  division_head: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผลการประเมิน" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "จัดการทีม",
      items: [
        { id: "dh-assess", ic: "", lb: "อนุมัติการประเมิน" },
        { id: "sup-gap", ic: "", lb: "ผลการประเมินของทีม" },
        { id: "dh-idp", ic: "", lb: "IDP & ติดตามทีม" }
      ]
    }
  ],
  academic_department_head: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผลการประเมิน" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "จัดการทีม",
      items: [
        { id: "dh-assess", ic: "", lb: "อนุมัติการประเมิน" },
        { id: "sup-gap", ic: "", lb: "ผลการประเมินของทีม" },
        { id: "dh-idp", ic: "", lb: "IDP & ติดตามทีม" }
      ]
    }
  ],
  manager_dept: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผลการประเมิน" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "จัดการทีม",
      items: [
        { id: "sup-gap", ic: "", lb: "ผลการประเมินของทีม" },
        { id: "dh-idp", ic: "", lb: "IDP & ติดตามทีม" }
      ]
    }
  ],
  manager: [
    {
      sec: "ภาพรวมคณะ",
      items: [
        { id: "faculty-assessment-overview", ic: "", lb: "ภาพรวมการประเมิน" },
        { id: "faculty-idp-tracking", ic: "", lb: "การติดตาม IDP" },
      ]
    },
    {
      sec: "รายการที่ต้องพิจารณา",
      items: [
        { id: "mgr-assessment-approval", ic: "", lb: "อนุมัติการประเมิน" },
        { id: "mgr-idp-approval", ic: "", lb: "แผนและผล IDP" }
      ]
    }
  ],
  dean: [
    {
      sec: "ภาพรวมคณะ",
      items: [
        { id: "faculty-assessment-overview", ic: "", lb: "ภาพรวมการประเมิน" },
        { id: "faculty-idp-tracking", ic: "", lb: "การติดตาม IDP" }
      ]
    },
    {
      sec: "รายการที่ต้องพิจารณา",
      items: [
        { id: "mgr-assessment-approval", ic: "", lb: "อนุมัติการประเมิน" },
        { id: "mgr-idp-approval", ic: "", lb: "แผนและผล IDP" }
      ]
    }
  ],
  hr: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "ผลการประเมิน" },
        { id: "emp-idp", ic: "", lb: "IDP ของฉัน" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" },
        { id: "emp-idp-detail", ic: "", lb: "รายละเอียด IDP" }
      ]
    },
    {
      sec: "HR",
      items: [
        { id: "hr-cycle", ic: "", lb: "รอบการประเมิน" },
        { id: "hr-position-competencies", ic: "", lb: "กำหนดสมรรถนะประจำตำแหน่ง" },
        { id: "hr-template", ic: "", lb: "กำหนดความคาดหวัง" }
      ]
    },
    {
      sec: "ภาพรวมคณะ",
      items: [
        { id: "faculty-assessment-overview", ic: "", lb: "ภาพรวมการประเมิน" },
        { id: "faculty-idp-tracking", ic: "", lb: "การติดตาม IDP" }
      ]
    }
  ],
  admin: [
    {
      sec: "ของฉัน (บุคลากร)",
      items: [
        { id: "emp-assess", ic: "", lb: "ประเมินตนเอง" },
        { id: "emp-gap", ic: "", lb: "สรุปผลสมรรถนะ" },
        { id: "emp-idp", ic: "", lb: "แผนพัฒนา IDP" },
        { id: "emp-progress", ic: "", lb: "อัปเดตความก้าวหน้า" }
      ]
    },
    {
      sec: "ระบบ",
      items: [
        { id: "admin-users", ic: "", lb: "จัดการผู้ใช้งาน" },
        { id: "admin-org-structure", ic: "", lb: "จัดการโครงสร้างองค์กร & สมรรถนะ" },
        { id: "admin-dict", ic: "", lb: "พจนานุกรมสมรรถนะ" },
        { id: "admin-idp-tools", ic: "", lb: "เครื่องมือ / แนวทาง สำหรับทำแผน IDP" }
      ]
    }
  ]
};

export const PAGE_TITLES = {
  "profile": "โปรไฟล์",
  "emp-assess": "ประเมินตนเอง",
  "emp-gap": "สรุปผลสมรรถนะ",
  "emp-idp": "แผนพัฒนารายบุคคล",
  "emp-progress": "อัปเดตความก้าวหน้า",
  "emp-idp-detail": "รายละเอียด IDP",
  "sup-assess": "ประเมินทีมงาน",
  "sup-gap": "ผลการประเมินของทีม",
  "sup-idp": "IDP & ติดตามทีม",
  "dh-assess": "อนุมัติการประเมิน",
  "dh-idp": "IDP & ติดตามทีม",
  "dept-monitor": "ภาพรวมหน่วยงาน (Department View)",
  "mgr-gap": "ภาพรวมองค์กร",
  "mgr-idp": "ติดตาม IDP ภาพรวม",
  "mgr-assessment-approval": "อนุมัติการประเมิน",
  "mgr-idp-approval": "อนุมัติแผนและผล IDP",
  "admin-fc-topic-review": "พิจารณาหัวข้อการประเมิน",
  "admin-assessment-review": "อนุมัติการประเมิน",
  "admin-team-assessment": "ผลการประเมินของทีม",
  "admin-idp-review": "อนุมัติแผนและผล IDP",
  "hr-cycle": "รอบการประเมิน",
  "hr-template": "กำหนดความคาดหวัง",
  "hr-position-competencies": "กำหนดสมรรถนะ",
  "hr-monitor": "ติดตามภาพรวม",
  "faculty-assessment-overview": "ภาพรวมการประเมิน",
  "faculty-idp-tracking": "การติดตาม IDP",
  "hr-comp-overview": "ภาพรวม Competency คณะ",
  "hr-idp-overview": "ภาพรวม IDP คณะ",
  "admin-users": "จัดการผู้ใช้งาน",
  "admin-org-structure": "จัดการโครงสร้างองค์กร & สมรรถนะ",
  "admin-dict": "พจนานุกรมสมรรถนะ",
  "admin-idp-tools": "เครื่องมือ / แนวทาง สำหรับทำแผน IDP"
};
