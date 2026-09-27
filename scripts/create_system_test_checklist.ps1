param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\docs\test')
)

$ErrorActionPreference = 'Stop'

$wdCollapseEnd = 0
$wdPageBreak = 7
$wdAlignParagraphLeft = 0
$wdAlignParagraphCenter = 1
$wdAlignParagraphRight = 2
$wdCellAlignVerticalCenter = 1
$wdPaperLetter = 2
$wdExportFormatPDF = 17
$wdExportOptimizeForPrint = 0
$wdExportAllDocument = 0
$wdExportDocumentContent = 0
$wdExportCreateHeadingBookmarks = 1
$wdFieldPage = 33
$wdFieldNumPages = 26
$wdGoToPage = 1
$wdGoToAbsolute = 1
$wdPasteEnhancedMetafile = 9
$ppLayoutBlank = 12
$ppSaveAsPNG = 18

$navy = 0x3C2413
$red = 0x3747A5
$lightRed = 0xE9E8F7
$lightGray = 0xF3F4F6
$midGray = 0x666666
$white = 0xFFFFFF
$border = 0xD6D9DE
$green = 0x557A35

$outputDirectory = [System.IO.Path]::GetFullPath($OutputDirectory)
$docxPath = Join-Path $outputDirectory 'EN-IDP-System-Test-Checklist.docx'
$pdfPath = Join-Path $outputDirectory 'EN-IDP-System-Test-Checklist.pdf'
$previewDirectory = Join-Path $outputDirectory '_preview'
$logoPath = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\public\images\EN-IDP-logo.svg'))

New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
if (Test-Path $previewDirectory) { Remove-Item -LiteralPath $previewDirectory -Recurse -Force }
New-Item -ItemType Directory -Force -Path $previewDirectory | Out-Null

function TestCase([string]$id, [string]$check, [string]$expected) {
    return [pscustomobject]@{ Id = $id; Check = $check; Expected = $expected }
}

$sections = @(
    [pscustomobject]@{
        Code = 'ENV'; Title = 'การเตรียมสภาพแวดล้อมและ Smoke Test'; Owner = 'ผู้ดูแลระบบ / QA'
        Cases = @(
            (TestCase 'ENV-01' 'เปิดหน้าแรกของระบบจาก URL ที่กำหนด' 'หน้า EN-IDP แสดงครบ ไม่มีภาพหรือข้อความเสียหาย'),
            (TestCase 'ENV-02' 'เปิดหน้า Login และตรวจชื่อระบบ/โลโก้' 'แสดง EN-IDP ถูกต้องและฟอร์มพร้อมใช้งาน'),
            (TestCase 'ENV-03' 'ตรวจการเชื่อมต่อ PostgreSQL และ migration status' 'เชื่อมต่อได้ และ migration ทุกไฟล์เป็น Ran'),
            (TestCase 'ENV-04' 'ตรวจบัญชีทดสอบครบทุกบทบาท' 'มี employee, supervisor, dept_head, dean, hr และ admin ที่ active'),
            (TestCase 'ENV-05' 'ล็อกอินผ่าน /mock-sso ใน local ด้วยแต่ละบทบาท' 'เข้าสู่ dashboard ของบทบาทนั้นได้'),
            (TestCase 'ENV-06' 'เปิด dashboard แล้ว refresh หน้าโดยตรง' 'ยังคง session และแสดงหน้าเดิมโดยไม่เกิด 404/500'),
            (TestCase 'ENV-07' 'ตรวจ Console และ Network หลังโหลดหน้าหลัก' 'ไม่มี JavaScript error และ request สำคัญไม่ล้มเหลว'),
            (TestCase 'ENV-08' 'ออกจากระบบแล้วกดย้อนกลับ' 'ไม่เห็นข้อมูลภายในระบบและถูกนำไปหน้า Login')
        )
    },
    [pscustomobject]@{
        Code = 'AUTH'; Title = 'การเข้าสู่ระบบ บัญชี และสิทธิ์การเข้าถึง'; Owner = 'QA / Admin'
        Cases = @(
            (TestCase 'AUTH-01' 'ล็อกอินด้วยอีเมลและรหัสผ่านที่ถูกต้อง' 'เข้าสู่ dashboard สำเร็จ'),
            (TestCase 'AUTH-02' 'ล็อกอินด้วย username ที่ Admin กำหนด' 'เข้าสู่ระบบด้วย username สำเร็จ'),
            (TestCase 'AUTH-03' 'กรอกรหัสผ่านไม่ถูกต้อง' 'ปฏิเสธการเข้าสู่ระบบและไม่สร้าง session'),
            (TestCase 'AUTH-04' 'กรอกอีเมล/username ที่ไม่มีในระบบ' 'แสดงข้อความข้อมูลเข้าสู่ระบบไม่ถูกต้อง'),
            (TestCase 'AUTH-05' 'ล็อกอินด้วยบัญชีที่ถูกระงับ' 'ระบบปฏิเสธและแจ้งว่าบัญชีถูกระงับ'),
            (TestCase 'AUTH-06' 'ลองเข้าสู่ระบบผิดซ้ำเกินจำนวนที่กำหนด' 'ระบบ rate limit และแจ้งให้ลองใหม่ภายหลัง'),
            (TestCase 'AUTH-07' 'ผู้ใช้ทั่วไปเปิด URL ของ Admin โดยตรง' 'ได้รับ 403 หรือถูกปฏิเสธตาม middleware'),
            (TestCase 'AUTH-08' 'Admin เปิดเมนูของ HR ที่จำกัดสิทธิ์' 'สิทธิ์เป็นไปตาม role middleware ที่กำหนด'),
            (TestCase 'AUTH-09' 'เปลี่ยนรหัสผ่านโดยกรอกรหัสเดิมถูกต้อง' 'รหัสใหม่ใช้งานได้และรหัสเดิมใช้ไม่ได้'),
            (TestCase 'AUTH-10' 'เปลี่ยนรหัสผ่านโดยกรอกรหัสเดิมผิด' 'ไม่แก้รหัสผ่านและแสดง validation error'),
            (TestCase 'AUTH-11' 'เปิดเส้นทางสมัครสมาชิก/ลืมรหัสผ่าน' 'เส้นทางที่ปิดไว้ไม่สามารถใช้งานได้'),
            (TestCase 'AUTH-12' 'ตรวจ legacy role manager และ manager_dept' 'ระบบแปลเป็น dean และ dept_head อย่างสม่ำเสมอ')
        )
    },
    [pscustomobject]@{
        Code = 'ADM-USR'; Title = 'Admin: จัดการผู้ใช้งานและสายผู้ประเมิน'; Owner = 'Admin'
        Cases = @(
            (TestCase 'ADM-USR-01' 'เพิ่มผู้ใช้โดยกรอกข้อมูลจำเป็นครบ' 'สร้างผู้ใช้และแสดงในรายการทันที'),
            (TestCase 'ADM-USR-02' 'เพิ่มผู้ใช้โดยไม่กรอก username/password' 'ไม่บันทึกและแสดง error ใกล้ช่องที่เกี่ยวข้อง'),
            (TestCase 'ADM-USR-03' 'เพิ่ม SSO, email หรือ username ซ้ำ' 'ระบบป้องกันค่าซ้ำและไม่สร้างแถวใหม่'),
            (TestCase 'ADM-USR-04' 'เลือกสายงาน กลุ่มงาน ตำแหน่ง และระดับที่สัมพันธ์กัน' 'บันทึกทั้งข้อความและ position_id/level_id ถูกต้อง'),
            (TestCase 'ADM-USR-05' 'ส่งค่าตำแหน่งที่ไม่อยู่ในโครงสร้าง' 'backend ปฏิเสธข้อมูลแม้แก้ payload เอง'),
            (TestCase 'ADM-USR-06' 'แก้ข้อมูลผู้ใช้โดยไม่เปลี่ยนรหัสผ่าน' 'ข้อมูลเปลี่ยน แต่ hash รหัสผ่านเดิมคงอยู่'),
            (TestCase 'ADM-USR-07' 'แก้ข้อมูลพร้อมตั้งรหัสผ่านใหม่และ confirmation' 'บันทึกรหัสใหม่และล็อกอินได้'),
            (TestCase 'ADM-USR-08' 'ระงับและเปิดใช้งานบัญชีอื่น' 'สถานะเปลี่ยนและมีผลต่อการล็อกอิน'),
            (TestCase 'ADM-USR-09' 'พยายามระงับบัญชี Admin ที่กำลังใช้งาน' 'ระบบปฏิเสธเพื่อป้องกันล็อกตัวเองออก'),
            (TestCase 'ADM-USR-10' 'กำหนด assessment reviewer หลายลำดับ' 'สร้าง user_reviewer_steps เรียง step_order ถูกต้อง'),
            (TestCase 'ADM-USR-11' 'กำหนด IDP reviewer chain แยกจาก assessment' 'สอง chain แยกกันและไม่เขียนทับกัน'),
            (TestCase 'ADM-USR-12' 'ค้นหา/กรองผู้ใช้ตามรหัส ชื่อ หน่วยงาน บทบาท และสถานะ' 'ผลลัพธ์ตรงเงื่อนไขและจำนวนรายการถูกต้อง')
        )
    },
    [pscustomobject]@{
        Code = 'ADM-ORG'; Title = 'Admin: โครงสร้างสายงานและตำแหน่ง'; Owner = 'Admin'
        Cases = @(
            (TestCase 'ADM-ORG-01' 'เพิ่ม แก้ไข และลบสายงาน' 'ข้อมูล master เปลี่ยนตามคำสั่งและ validation ทำงาน'),
            (TestCase 'ADM-ORG-02' 'เพิ่มกลุ่มงาน/ภาควิชาภายใต้สายงาน' 'สร้างความสัมพันธ์ workline และ job family ถูกต้อง'),
            (TestCase 'ADM-ORG-03' 'เพิ่มตำแหน่งภายใต้กลุ่มงาน' 'ตำแหน่งผูก job_family_id ถูกต้อง'),
            (TestCase 'ADM-ORG-04' 'เพิ่มฝ่าย งาน และหน่วยในสายสนับสนุน' 'ลำดับ ฝ่าย > งาน > หน่วย แสดงและบันทึกถูกต้อง'),
            (TestCase 'ADM-ORG-05' 'เพิ่มตำแหน่งชื่อเดียวกันในคนละหน่วย' 'เพิ่มได้ และแต่ละแถวมี support_unit_id ต่างกัน'),
            (TestCase 'ADM-ORG-06' 'เพิ่มตำแหน่งชื่อซ้ำภายในหน่วยเดียวกัน' 'ระบบปฏิเสธด้วย validation โดยไม่เกิด SQL error'),
            (TestCase 'ADM-ORG-07' 'แก้ชื่อตำแหน่งที่มีชื่อซ้ำในอีกหน่วย' 'แก้เฉพาะตำแหน่งและผู้ใช้ในหน่วยที่เลือก'),
            (TestCase 'ADM-ORG-08' 'เปลี่ยนชื่อสายงานที่มีผู้ใช้สังกัดอยู่' 'users.workline ถูก sync เป็นชื่อใหม่'),
            (TestCase 'ADM-ORG-09' 'เปลี่ยนชื่อกลุ่มงาน/ภาควิชา' 'prefix ของ users.department ถูก sync โดยไม่เสีย path ต่อท้าย'),
            (TestCase 'ADM-ORG-10' 'เพิ่มระดับตำแหน่งและ Expected Level พื้นฐาน' 'ระดับถูกผูกสายงานและค่าคาดหวังอยู่ในช่วง 1-5'),
            (TestCase 'ADM-ORG-11' 'ใช้ชื่อระดับเดียวกันในคนละสายงาน' 'เพิ่มได้ แต่ห้ามซ้ำในสายงานเดียวกัน'),
            (TestCase 'ADM-ORG-12' 'กด Enter ใน modal เพิ่ม/แก้ไขรายการ' 'trigger ปุ่มหลักหนึ่งครั้งและไม่ยิงซ้ำ'),
            (TestCase 'ADM-ORG-13' 'กด Enter ขณะอยู่ใน textarea/select' 'ยังคงพฤติกรรมกรอกข้อมูล ไม่บันทึกโดยไม่ตั้งใจ'),
            (TestCase 'ADM-ORG-14' 'ลบ master ที่ถูกอ้างอิง' 'ผลลัพธ์เป็นไปตาม FK และระบบไม่ทิ้งข้อมูลเสียหาย')
        )
    },
    [pscustomobject]@{
        Code = 'ADM-COMP'; Title = 'Admin: สมรรถนะและเครื่องมือพัฒนา'; Owner = 'Admin'
        Cases = @(
            (TestCase 'ADM-COMP-01' 'เพิ่มประเภทสมรรถนะด้วย code ใหม่' 'สร้างประเภทพร้อมชื่อเต็มและรายละเอียด'),
            (TestCase 'ADM-COMP-02' 'เพิ่ม code ประเภทสมรรถนะซ้ำ' 'ระบบปฏิเสธและคงข้อมูลเดิม'),
            (TestCase 'ADM-COMP-03' 'เพิ่มสมรรถนะพร้อมระดับและพฤติกรรมบ่งชี้' 'ข้อมูล competency, level และ indicator ครบ'),
            (TestCase 'ADM-COMP-04' 'แก้ไขสมรรถนะและ indicator' 'ข้อมูลใหม่แสดงทุกหน้าที่เกี่ยวข้อง'),
            (TestCase 'ADM-COMP-05' 'นำเข้าสมรรถนะจากไฟล์ที่ถูกต้อง' 'เพิ่มข้อมูลตามไฟล์และรายงานผลสำเร็จ'),
            (TestCase 'ADM-COMP-06' 'นำเข้าไฟล์ชนิด/คอลัมน์ไม่ถูกต้อง' 'ไม่บันทึกบางส่วนและแจ้งข้อผิดพลาดชัดเจน'),
            (TestCase 'ADM-COMP-07' 'เพิ่มเครื่องมือ Experiential/Social Learning' 'รายการ active แสดงในฟอร์ม IDP หมวดที่ถูกต้อง'),
            (TestCase 'ADM-COMP-08' 'เพิ่ม Learning Catalog และรูปแบบ e-Learning/In-class' 'บันทึกรหัส ชื่อ delivery และสถานะครบ'),
            (TestCase 'ADM-COMP-09' 'ผูก Learning Catalog กับสมรรถนะหลายรายการ' 'pivot mapping ถูกต้องและไม่เกิดรายการซ้ำ'),
            (TestCase 'ADM-COMP-10' 'ปิดใช้งานเครื่องมือหรือหลักสูตร' 'ไม่แสดงเป็นตัวเลือกใหม่ แต่ข้อมูลเดิมยังอ่านได้')
        )
    },
    [pscustomobject]@{
        Code = 'ADM-CHAIN'; Title = 'Admin: Template สายผู้ประเมิน'; Owner = 'Admin'
        Cases = @(
            (TestCase 'ADM-CHAIN-01' 'สร้าง assessment reviewer template' 'สร้าง template และ steps ตามลำดับ'),
            (TestCase 'ADM-CHAIN-02' 'สร้าง IDP reviewer template' 'template ถูกเก็บด้วย chain_type=idp'),
            (TestCase 'ADM-CHAIN-03' 'เพิ่ม fixed reviewer หลายลำดับ' 'ผู้ตรวจแต่ละ step ถูก resolve ถูกคน'),
            (TestCase 'ADM-CHAIN-04' 'ใช้ resolver ตามบทบาท/หน่วยงาน/สายงาน' 'ได้ reviewer ที่ active และตรง scope'),
            (TestCase 'ADM-CHAIN-05' 'นำ template ไปใช้กับผู้ใช้' 'คัดลอกผลลัพธ์ลง user_reviewer_steps'),
            (TestCase 'ADM-CHAIN-06' 'เปลี่ยน assessment template' 'แทนเฉพาะ assessment chain ไม่กระทบ IDP chain'),
            (TestCase 'ADM-CHAIN-07' 'เพิ่ม/ถอดสมาชิก template' 'assignment และ reviewer steps ของ flow นั้นอัปเดตถูกต้อง'),
            (TestCase 'ADM-CHAIN-08' 'แก้ไข template ที่เปิดใช้งาน' 'สมาชิกได้รับ chain ที่ resolve ใหม่ตามข้อกำหนด'),
            (TestCase 'ADM-CHAIN-09' 'ลบ template ที่มีสมาชิก' 'จัดการ assignment ตามกติกาและไม่เหลือ orphan'),
            (TestCase 'ADM-CHAIN-10' 'ผู้ใช้ไม่มี assessment หรือ IDP chain' 'Admin เห็นคำเตือน และ workflow ที่เกี่ยวข้องถูกบล็อก')
        )
    },
    [pscustomobject]@{
        Code = 'HR'; Title = 'HR: รอบประเมินและการผูกสมรรถนะกับตำแหน่ง'; Owner = 'HR'
        Cases = @(
            (TestCase 'HR-01' 'สร้างรอบประเมินพร้อมช่วงวัน' 'บันทึกรอบและ validation วันที่ถูกต้อง'),
            (TestCase 'HR-02' 'เปิดใช้งานรอบประเมินใหม่' 'มี active round ตามกติกาและ dashboard เปลี่ยนรอบ'),
            (TestCase 'HR-03' 'แก้ไขรอบประเมินที่ยังแก้ได้' 'ข้อมูลชื่อ/วันเปลี่ยนและแสดงทุกบทบาท'),
            (TestCase 'HR-04' 'ทดสอบก่อนวันเริ่มและหลัง deadline' 'การแก้/ส่งแบบประเมินถูกจำกัดตามช่วงเวลา'),
            (TestCase 'HR-05' 'ผูก CC/MC/FC กับตำแหน่งในรอบ' 'position_competencies เก็บ round/position/competency ถูกต้อง'),
            (TestCase 'HR-06' 'ลบ competency ออกจากตำแหน่ง' 'mapping ถูกลบและข้อมูล assessment sync ตามกติกา'),
            (TestCase 'HR-07' 'คัดลอก mapping จากรอบก่อน' 'คัดลอกครบโดยไม่สร้าง duplicate'),
            (TestCase 'HR-08' 'กำหนด Expected Level ตาม scope' 'ระบบ resolve ค่า precedence ถูกต้อง'),
            (TestCase 'HR-09' 'ตั้งจำนวน FC ที่ต้องเลือกเป็น 0' 'พนักงานไม่เข้าสู่ขั้นตอน FC pre-selection'),
            (TestCase 'HR-10' 'ตั้งจำนวน FC มากกว่า 0' 'พนักงานต้องเลือก FC ก่อนประเมินทั้งหมด'),
            (TestCase 'HR-11' 'ตั้งจำนวน FC มากกว่าหัวข้อ FC ที่ผูกไว้' 'ระบบปฏิเสธค่าที่เป็นไปไม่ได้'),
            (TestCase 'HR-12' 'ตำแหน่งหรือผู้ใช้ยังไม่ถูก mapping' 'HR เห็นคำเตือน/จำนวน unmapped ที่ถูกต้อง'),
            (TestCase 'HR-13' 'ส่งอีเมลเตือนผู้ที่ยังไม่ประเมิน' 'ส่งเฉพาะผู้ใช้เป้าหมายและแสดงผลสำเร็จ')
        )
    },
    [pscustomobject]@{
        Code = 'EMP-ASSESS'; Title = 'พนักงาน: โปรไฟล์และการประเมินสมรรถนะ'; Owner = 'Employee'
        Cases = @(
            (TestCase 'EMP-ASSESS-01' 'เปิดโปรไฟล์และตรวจข้อมูลจาก Admin' 'SSO/สังกัด/ตำแหน่ง/ระดับแสดงถูกต้อง'),
            (TestCase 'EMP-ASSESS-02' 'แก้ข้อมูลส่วนตัวที่อนุญาต' 'บันทึกสำเร็จและข้อมูล Admin-owned ไม่เปลี่ยน'),
            (TestCase 'EMP-ASSESS-03' 'แก้ SSO/ตำแหน่งผ่าน payload' 'backend ปฏิเสธหรือเพิกเฉยค่าที่ห้ามแก้'),
            (TestCase 'EMP-ASSESS-04' 'เปิดแบบประเมินเมื่อไม่มี reviewer chain' 'ระบบล็อกและแจ้งให้ติดต่อผู้ดูแล'),
            (TestCase 'EMP-ASSESS-05' 'เปิดแบบประเมินเมื่อไม่มี competency mapping' 'แสดง empty/warning state ชัดเจน'),
            (TestCase 'EMP-ASSESS-06' 'ตรวจรายการ CC/MC/FC ตามตำแหน่งและรอบ' 'เห็นเฉพาะหัวข้อที่ HR ผูกไว้'),
            (TestCase 'EMP-ASSESS-07' 'เลือก behavior indicators และบันทึกร่าง' 'เก็บ assessment_indicator_results และโหลดกลับได้'),
            (TestCase 'EMP-ASSESS-08' 'refresh หลังบันทึกร่าง' 'ค่าที่เลือกและหมายเหตุยังอยู่'),
            (TestCase 'EMP-ASSESS-09' 'ส่งแบบประเมินที่ข้อมูลไม่ครบ' 'ไม่ส่งและชี้ช่อง/หัวข้อที่ขาด'),
            (TestCase 'EMP-ASSESS-10' 'ส่งแบบประเมินครบ' 'สถานะเป็น self_submitted และล็อกการแก้ไข'),
            (TestCase 'EMP-ASSESS-11' 'แก้ assessment หลังส่งด้วย request ตรง' 'backend ปฏิเสธการแก้สถานะที่ล็อก'),
            (TestCase 'EMP-ASSESS-12' 'รายการถูกส่งกลับ revision_required' 'เปิดแก้ได้พร้อมเห็นเหตุผลที่ส่งกลับ'),
            (TestCase 'EMP-ASSESS-13' 'ส่งแก้ไขอีกครั้ง' 'เริ่ม reviewer chain ใหม่จาก step แรกตามกติกา')
        )
    },
    [pscustomobject]@{
        Code = 'FC'; Title = 'การเลือกและอนุมัติหัวข้อ Functional Competency'; Owner = 'Employee / Reviewer 1'
        Cases = @(
            (TestCase 'FC-01' 'ตำแหน่งกำหนด required_fc_count > 0' 'หน้าประเมินทั้งหมดถูกล็อกก่อน FC อนุมัติ'),
            (TestCase 'FC-02' 'เลือก FC น้อย/มากกว่าจำนวนที่กำหนด' 'ไม่สามารถส่งคำขอได้'),
            (TestCase 'FC-03' 'เลือก FC ครบจำนวนจากรายการที่ HR ผูกไว้' 'ส่งคำขอไป reviewer คนแรกสำเร็จ'),
            (TestCase 'FC-04' 'ส่ง competency ที่ไม่อยู่ในตำแหน่งผ่าน payload' 'backend ปฏิเสธคำขอ'),
            (TestCase 'FC-05' 'ผู้ใช้ที่ไม่ใช่ reviewer คนแรกพยายามอนุมัติ' 'ได้รับ 403/ปฏิเสธสิทธิ์'),
            (TestCase 'FC-06' 'reviewer คนแรกอนุมัติหัวข้อ' 'สถานะ approved และเปิด self-assessment'),
            (TestCase 'FC-07' 'reviewer ส่งกลับโดยไม่กรอกเหตุผล' 'ระบบบังคับ comment'),
            (TestCase 'FC-08' 'reviewer ส่งกลับพร้อมเหตุผล' 'สถานะ revision_required และพนักงานแก้รายการได้'),
            (TestCase 'FC-09' 'พนักงานส่งรายการ FC แก้ไขใหม่' 'รายการเดิมถูกแทนอย่างถูกต้องและรอ reviewer อีกครั้ง'),
            (TestCase 'FC-10' 'หลังอนุมัติ ตรวจหัวข้อ FC ในแบบประเมิน' 'แสดงเฉพาะ FC ที่อนุมัติแล้ว')
        )
    },
    [pscustomobject]@{
        Code = 'REVIEW'; Title = 'สายการอนุมัติผลประเมิน'; Owner = 'Reviewer / Dept Head / Dean'
        Cases = @(
            (TestCase 'REVIEW-01' 'reviewer step 1 เปิดรายการรอตรวจ' 'เห็นเฉพาะพนักงานที่ตนรับผิดชอบ'),
            (TestCase 'REVIEW-02' 'ผู้ไม่อยู่ใน active step พยายาม approve' 'ระบบปฏิเสธสิทธิ์'),
            (TestCase 'REVIEW-03' 'reviewer ให้คะแนน/หมายเหตุและอนุมัติ' 'บันทึก scores และเลื่อนไป step ถัดไป'),
            (TestCase 'REVIEW-04' 'reviewer ส่งกลับโดยไม่มี comment' 'ไม่อนุญาตให้ส่งกลับ'),
            (TestCase 'REVIEW-05' 'reviewer ส่งกลับพร้อม comment' 'assessment และ gap กลับ revision_required'),
            (TestCase 'REVIEW-06' 'พนักงานแก้ไขและส่งใหม่' 'รายการกลับไป reviewer step แรก'),
            (TestCase 'REVIEW-07' 'อนุมัติผ่าน step 2 และ 3' 'สถานะเปลี่ยนตาม chain จริง ไม่ยึด role แบบ hard-code'),
            (TestCase 'REVIEW-08' 'chain มี step 4 ขึ้นไป' 'ใช้สถานะ review_step_N และสิทธิ์ถูกคน'),
            (TestCase 'REVIEW-09' 'อนุมัติ reviewer คนสุดท้าย' 'สถานะ canonical เป็น approved'),
            (TestCase 'REVIEW-10' 'ตรวจ legacy dean_approved' 'ระบบยังอ่านเป็นผลอนุมัติได้ แต่ไม่เขียนค่าใหม่'),
            (TestCase 'REVIEW-11' 'รีเฟรชหน้าหลังตัดสินใจ' 'รายการหายจากคิวหรือย้ายสถานะทันที'),
            (TestCase 'REVIEW-12' 'เปิดรายละเอียดหลักฐาน/indicator ของพนักงาน' 'ข้อมูลตรงกับสิ่งที่พนักงานส่ง'),
            (TestCase 'REVIEW-13' 'ทดสอบอนุมัติ request เดิมซ้ำ' 'ไม่สร้าง score/transition ซ้ำผิดปกติ')
        )
    },
    [pscustomobject]@{
        Code = 'GAP'; Title = 'ผลประเมินและ Competency Gap'; Owner = 'Employee / Reviewer / HR'
        Cases = @(
            (TestCase 'GAP-01' 'ผลยังไม่อนุมัติสุดท้าย' 'ไม่แสดงเป็นผล final และยังไม่สร้างงาน IDP'),
            (TestCase 'GAP-02' 'คำนวณ actual และ expected level' 'ใช้ค่า resolver และข้อมูลรอบที่ถูกต้อง'),
            (TestCase 'GAP-03' 'ตรวจสูตร gap' 'gap = actual_level - expected_level'),
            (TestCase 'GAP-04' 'gap ติดลบ' 'requires_idp=true'),
            (TestCase 'GAP-05' 'gap เท่ากับศูนย์หรือเป็นบวก' 'requires_idp=false'),
            (TestCase 'GAP-06' 'เปิดรายละเอียดผลและ reviewer comments' 'ข้อมูลครบและเป็น read-only หลังอนุมัติ'),
            (TestCase 'GAP-07' 'เปลี่ยนรอบประเมินที่กำลังดู' 'แสดงผลเฉพาะรอบที่เลือก ไม่ปะปนกัน'),
            (TestCase 'GAP-08' 'ตรวจจำนวน gap ใน dashboard/analytics' 'จำนวนไม่ซ้ำจาก competency หรือ reviewer score หลายแถว')
        )
    },
    [pscustomobject]@{
        Code = 'IDP'; Title = 'พนักงาน: จัดทำแผนพัฒนารายบุคคล'; Owner = 'Employee'
        Cases = @(
            (TestCase 'IDP-01' 'เปิด IDP เมื่อไม่มี approved negative gap' 'ไม่มีรายการให้สร้างและไม่สามารถส่ง payload แทรกได้'),
            (TestCase 'IDP-02' 'เปิด IDP เมื่อมี negative gap ที่อนุมัติแล้ว' 'สร้าง/แสดงหนึ่ง idp_item ต่อหนึ่ง competency gap'),
            (TestCase 'IDP-03' 'เพิ่มหลายกิจกรรมใน competency เดียวกัน' 'เก็บหลาย idp_activities โดยไม่เขียนทับกัน'),
            (TestCase 'IDP-04' 'เลือก Experiential Learning' 'ตัวเลือกมาจาก active idp_learning_methods หมวด experiential'),
            (TestCase 'IDP-05' 'เลือก Social Learning' 'ตัวเลือกมาจาก active idp_learning_methods หมวด social'),
            (TestCase 'IDP-06' 'เลือก Formal Learning' 'เห็นเฉพาะ catalog ที่ผูกกับ competency gap นี้'),
            (TestCase 'IDP-07' 'ส่ง catalog ของ competency อื่นผ่าน payload' 'backend ปฏิเสธรายการ'),
            (TestCase 'IDP-08' 'กรอกวันสิ้นสุดก่อนวันเริ่ม' 'validation ปฏิเสธและแสดงข้อความใกล้ช่อง'),
            (TestCase 'IDP-09' 'บันทึกร่างข้อมูลไม่ครบ' 'บันทึกเฉพาะรายการที่ยัง editable ตามกติกา draft'),
            (TestCase 'IDP-10' 'refresh หลัง auto-save' 'กิจกรรม เป้าหมาย น้ำหนัก และรายละเอียดกลับมาครบ'),
            (TestCase 'IDP-11' 'น้ำหนักกิจกรรมรวมไม่เท่ากับ 100%' 'ไม่อนุญาตให้ submit item'),
            (TestCase 'IDP-12' 'น้ำหนักรวม 100% และข้อมูลครบ' 'ส่ง competency item นั้นเข้าสายอนุมัติ'),
            (TestCase 'IDP-13' 'ส่งหนึ่ง item ขณะที่ item อื่นยัง draft' 'ส่งได้โดยไม่บังคับทุก competency พร้อมกัน'),
            (TestCase 'IDP-14' 'แก้ item ที่อยู่ review_step_N หรือ approved' 'UI ล็อกและ backend ปฏิเสธ'),
            (TestCase 'IDP-15' 'item ถูกส่งกลับ revision_required' 'แสดง comment และเปิดแก้เฉพาะ item นั้น'),
            (TestCase 'IDP-16' 'ส่ง revision ใหม่' 'submission_version เพิ่มและเริ่ม IDP chain ใหม่')
        )
    },
    [pscustomobject]@{
        Code = 'IDP-REV'; Title = 'การอนุมัติแผน IDP รายสมรรถนะ'; Owner = 'IDP Reviewer'
        Cases = @(
            (TestCase 'IDP-REV-01' 'reviewer เปิดคิว IDP' 'เห็นเฉพาะ item ที่รอตนตาม chain_type=idp'),
            (TestCase 'IDP-REV-02' 'เปิดรายละเอียดกิจกรรมทั้งหมดใน item' 'ข้อมูลหลาย activity ครบและน้ำหนักรวมถูกต้อง'),
            (TestCase 'IDP-REV-03' 'ผู้ไม่ใช่ current reviewer พยายาม approve' 'ระบบปฏิเสธสิทธิ์'),
            (TestCase 'IDP-REV-04' 'อนุมัติ step ปัจจุบัน' 'สร้าง idp_item_reviews และเลื่อนไป step ถัดไป'),
            (TestCase 'IDP-REV-05' 'ส่งกลับโดยไม่กรอก comment' 'ระบบบังคับเหตุผล'),
            (TestCase 'IDP-REV-06' 'ส่งกลับพร้อม comment' 'item เป็น revision_required และบันทึก decision'),
            (TestCase 'IDP-REV-07' 'อนุมัติ step สุดท้าย' 'item เป็น approved และเปิดการติดตามความคืบหน้า'),
            (TestCase 'IDP-REV-08' 'ตรวจ review history หลาย submission_version' 'ประวัติ append-only ไม่เขียนทับรอบก่อน'),
            (TestCase 'IDP-REV-09' 'หลาย item ในแผนมีสถานะต่างกัน' 'parent idps.status ถูก derive ถูกต้อง'),
            (TestCase 'IDP-REV-10' 'อนุมัติ item ซ้ำด้วย request เดิม' 'ไม่เกิด review/transition ซ้ำ'),
            (TestCase 'IDP-REV-11' 'ตรวจ assessment chain และ IDP chain คนละชุด' 'สิทธิ์ approval ใช้ chain ที่ถูก workflow'),
            (TestCase 'IDP-REV-12' 'พนักงานดูสถานะหลัง reviewer ตัดสินใจ' 'สถานะและ comment ล่าสุดแสดงทันที')
        )
    },
    [pscustomobject]@{
        Code = 'PROG'; Title = 'ติดตามความคืบหน้า หลักฐาน และปิดแผน'; Owner = 'Employee / Reviewer'
        Cases = @(
            (TestCase 'PROG-01' 'อัปเดต progress ก่อน item approved' 'backend ปฏิเสธการอัปเดต'),
            (TestCase 'PROG-02' 'อัปเดตเปอร์เซ็นต์และรายละเอียดหลัง approved' 'สร้าง activity update และแสดง timeline'),
            (TestCase 'PROG-03' 'กรอก progress ต่ำกว่า 0 หรือเกิน 100' 'validation ปฏิเสธ'),
            (TestCase 'PROG-04' 'แนบ URL หลักฐานที่ถูกต้อง' 'เปิด URL ได้และเก็บ metadata ถูกต้อง'),
            (TestCase 'PROG-05' 'แนบไฟล์ชนิด/ขนาดที่อนุญาต' 'อัปโหลดสำเร็จและดาวน์โหลดผ่าน route ที่ป้องกันสิทธิ์'),
            (TestCase 'PROG-06' 'แนบไฟล์ชนิดหรือขนาดไม่ถูกต้อง' 'ไม่บันทึกและแจ้ง validation'),
            (TestCase 'PROG-07' 'ผู้ใช้อื่นเปิด UUID หลักฐานโดยตรง' 'ระบบตรวจสิทธิ์และไม่เปิดเผยไฟล์'),
            (TestCase 'PROG-08' 'บันทึก update ที่มีข้อมูลผลลัพธ์ตามแบบฟอร์ม' 'รายละเอียด form fields ถูกเก็บและโหลดกลับ'),
            (TestCase 'PROG-09' 'ส่งคำขอปิดกิจกรรม/แผน' 'สร้าง completion submission และล็อกการส่งซ้ำ'),
            (TestCase 'PROG-10' 'reviewer เปิดรายละเอียด completion' 'เห็นแผน update และหลักฐานครบ'),
            (TestCase 'PROG-11' 'reviewer อนุมัติ completion' 'สถานะ completion เปลี่ยนและผลลัพธ์แสดงแก่พนักงาน'),
            (TestCase 'PROG-12' 'reviewer ส่ง completion กลับพร้อมเหตุผล' 'พนักงานเห็นเหตุผลและแก้ไข/ส่งใหม่ได้'),
            (TestCase 'PROG-13' 'reviewer ส่งกลับโดยไม่มีเหตุผล' 'ระบบปฏิเสธ'),
            (TestCase 'PROG-14' 'ตรวจหลายกิจกรรมใน item เดียว' 'ความคืบหน้าแต่ละ activity ไม่เขียนทับกัน'),
            (TestCase 'PROG-15' 'ตรวจสถานะรวมเมื่อกิจกรรมทยอยเสร็จ' 'เปอร์เซ็นต์/สถานะสรุปคำนวณตรงข้อมูลจริง')
        )
    },
    [pscustomobject]@{
        Code = 'DASH'; Title = 'Dashboard รายงาน และ Analytics'; Owner = 'ทุกบทบาท'
        Cases = @(
            (TestCase 'DASH-01' 'เปิด dashboard แต่ละบทบาท' 'เมนูและหน้าเริ่มต้นตรง role'),
            (TestCase 'DASH-02' 'ตรวจจำนวนพนักงาน/รายการรออนุมัติ' 'ตัวเลขตรงฐานข้อมูลและไม่ double count'),
            (TestCase 'DASH-03' 'กรองข้อมูลตามสายงาน/หน่วยงาน/รอบ' 'กราฟ ตาราง และ summary ใช้ filter เดียวกัน'),
            (TestCase 'DASH-04' 'เปิด Faculty Overview' 'ข้อมูลภาพรวมโหลดได้แม้บางกลุ่มไม่มีข้อมูล'),
            (TestCase 'DASH-05' 'เปิดรายละเอียดฝ่ายสายสนับสนุน' 'แสดงหน่วย ตำแหน่ง และสถิติ scope นั้น'),
            (TestCase 'DASH-06' 'ตรวจ empty state' 'ไม่มี error และแสดงข้อความที่เข้าใจได้'),
            (TestCase 'DASH-07' 'ตรวจข้อมูล legacy role ใน dashboard' 'ผู้ใช้ถูกจัดเข้าหน้าบทบาท canonical ถูกต้อง'),
            (TestCase 'DASH-08' 'ตรวจเวลาตอบสนองหน้า dashboard ด้วยข้อมูลจำนวนมาก' 'query count/เวลาอยู่ในเกณฑ์โครงการ'),
            (TestCase 'DASH-09' 'refresh หลังเปลี่ยนข้อมูลจากอีกหน้าหนึ่ง' 'server-owned data ใหม่แสดงโดยไม่ค้างค่าเก่า')
        )
    },
    [pscustomobject]@{
        Code = 'NOTIFY'; Title = 'การแจ้งเตือนและอีเมล'; Owner = 'Admin / HR / QA'
        Cases = @(
            (TestCase 'NOTIFY-01' 'สร้างผู้ใช้ใหม่' 'แจ้ง Admin ตามช่องทางที่ตั้งค่า'),
            (TestCase 'NOTIFY-02' 'พนักงานส่งแบบประเมิน' 'แจ้ง reviewer step แรกที่ถูกต้อง'),
            (TestCase 'NOTIFY-03' 'reviewer อนุมัติ/ส่งกลับ' 'แจ้งผู้เกี่ยวข้องพร้อมสถานะและ comment ที่เหมาะสม'),
            (TestCase 'NOTIFY-04' 'ส่ง IDP item เข้าสายอนุมัติ' 'แจ้ง IDP reviewer ไม่ใช่ assessment reviewer โดยผิด flow'),
            (TestCase 'NOTIFY-05' 'ทดสอบ hourly digest' 'ไม่ส่งรายการซ้ำเกินกติกาและจำนวน recipient ถูกต้อง'),
            (TestCase 'NOTIFY-06' 'ทดสอบ daily digest' 'สรุป incomplete/missing expectation/pending ถูกต้อง'),
            (TestCase 'NOTIFY-07' 'ปิด notification toggle ใน local' 'ไม่ส่งอีเมลจริงขณะทดสอบ'),
            (TestCase 'NOTIFY-08' 'mail transport ล้มเหลว' 'ธุรกรรมหลักไม่เสียหายและมี log ตรวจสอบได้')
        )
    },
    [pscustomobject]@{
        Code = 'NFR'; Title = 'Regression, Usability, Security และ Non-functional'; Owner = 'QA / Developer'
        Cases = @(
            (TestCase 'NFR-01' 'ทดสอบหน้าจอ desktop 1366x768 และ 1920x1080' 'ไม่มีข้อความ/ปุ่มซ้อนและ workflow สำคัญอยู่ใน viewport'),
            (TestCase 'NFR-02' 'ทดสอบ breakpoint ใกล้ 900px และ mobile' 'เมนู ตาราง modal และฟอร์มไม่ล้นผิดปกติ'),
            (TestCase 'NFR-03' 'ใช้งานด้วย keyboard: Tab, Shift+Tab, Enter, Escape' 'focus order ชัดเจนและปุ่มหลัก modal ใช้งานได้'),
            (TestCase 'NFR-04' 'ตรวจ label, focus indicator และ contrast' 'ช่องกรอกและปุ่มสำคัญเข้าถึงได้'),
            (TestCase 'NFR-05' 'ส่ง request ไม่มี CSRF หรือ session หมดอายุ' 'ระบบปฏิเสธอย่างปลอดภัย'),
            (TestCase 'NFR-06' 'ทดสอบ XSS ในชื่อ/comment/รายละเอียด' 'ข้อความถูก escape และไม่มี script ทำงาน'),
            (TestCase 'NFR-07' 'ทดสอบ IDOR โดยเปลี่ยน user/item/evidence ID' 'backend ตรวจ ownership และ role ทุกครั้ง'),
            (TestCase 'NFR-08' 'ส่ง request ซ้ำอย่างรวดเร็ว' 'ไม่สร้าง duplicate records หรือ transition ซ้ำ'),
            (TestCase 'NFR-09' 'ตรวจ transaction ของ multi-table write' 'เมื่อขั้นใดล้มเหลวไม่มีข้อมูลบันทึกค้างบางส่วน'),
            (TestCase 'NFR-10' 'ตรวจข้อความ validation ภาษาไทย' 'ข้อความชัดเจนและอยู่ใกล้ field ที่ผิด'),
            (TestCase 'NFR-11' 'รัน focused feature tests ของโมดูลที่แก้' 'tests ผ่านทั้งหมด'),
            (TestCase 'NFR-12' 'รัน php artisan test ทั้งชุด' 'ไม่มี regression ใน backend workflow'),
            (TestCase 'NFR-13' 'รัน npm run build' 'Vite build สำเร็จโดยไม่มี compile error'),
            (TestCase 'NFR-14' 'ตรวจ php artisan migrate:status บน PostgreSQL' 'migration ที่จำเป็นเป็น Ran ครบ'),
            (TestCase 'NFR-15' 'สำรองและกู้คืนฐานข้อมูลทดสอบ' 'กู้คืนได้และ FK/index สำคัญยังครบ')
        )
    }
)

$automationMap = @(
    @('Authentication / Password', 'tests/Feature/Auth/*'),
    @('Admin users / reviewer templates', 'AdminUserControllerTest, DivisionHeadRoleTest'),
    @('Organization structure', 'AdminStructureControllerTest, AdminDashboardUserStructureSyncTest'),
    @('Competency master / catalog', 'AdminCompetency*Test, AdminLearningCatalogTest, AdminIdpLearningMethodControllerTest'),
    @('Assessment rounds / HR mapping', 'HrAssessmentRoundTest, HrPositionCompetencyTest, HrDashboardTest'),
    @('FC selection / assessment review', 'FcTopicSelectionFlowTest, AssessmentReviewerChainTest, AssessmentRoundDeadlineTest'),
    @('Gap / IDP plan and approval', 'EmployeeIdpPlanTest, IdpItemApprovalTest'),
    @('Progress / completion review', 'IdpActivityUpdateTest, IdpActivityProgressReviewTest'),
    @('Dashboard / analytics / performance', 'ManagerDashboardTest, FacultyAnalyticsDashboardTest, DashboardQueryPerformanceTest'),
    @('Notifications / profile / mock SSO', 'NotificationDigestServiceTest, ProfileTest, MockSsoLoginTest')
)

$word = $null
$doc = $null
$powerPoint = $null
$presentation = $null

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $doc = $word.Documents.Add()
    $selection = $word.Selection

    $pageSetup = $doc.Sections.Item(1).PageSetup
    $pageSetup.PaperSize = $wdPaperLetter
    $pageSetup.TopMargin = $word.InchesToPoints(0.72)
    $pageSetup.BottomMargin = $word.InchesToPoints(0.72)
    $pageSetup.LeftMargin = $word.InchesToPoints(1.0)
    $pageSetup.RightMargin = $word.InchesToPoints(1.0)
    $pageSetup.HeaderDistance = $word.InchesToPoints(0.42)
    $pageSetup.FooterDistance = $word.InchesToPoints(0.42)

    $normal = $doc.Styles.Item('Normal')
    $normal.Font.Name = 'Tahoma'
    $normal.Font.NameFarEast = 'Tahoma'
    $normal.Font.Size = 10
    $normal.Font.Color = $navy
    $normal.ParagraphFormat.SpaceAfter = 6
    $normal.ParagraphFormat.LineSpacingRule = 5
    $normal.ParagraphFormat.LineSpacing = 15

    $titleStyle = $doc.Styles.Item('Title')
    $titleStyle.Font.Name = 'Tahoma'
    $titleStyle.Font.NameFarEast = 'Tahoma'
    $titleStyle.Font.Size = 25
    $titleStyle.Font.Bold = $true
    $titleStyle.Font.Color = $navy
    $titleStyle.ParagraphFormat.SpaceAfter = 8

    $subtitleStyle = $doc.Styles.Item('Subtitle')
    $subtitleStyle.Font.Name = 'Tahoma'
    $subtitleStyle.Font.NameFarEast = 'Tahoma'
    $subtitleStyle.Font.Size = 12
    $subtitleStyle.Font.Color = $midGray
    $subtitleStyle.ParagraphFormat.SpaceAfter = 18

    foreach ($headingName in @('Heading 1', 'Heading 2', 'Heading 3')) {
        $style = $doc.Styles.Item($headingName)
        $style.Font.Name = 'Tahoma'
        $style.Font.NameFarEast = 'Tahoma'
        $style.Font.Bold = $true
        $style.Font.Color = $red
        $style.ParagraphFormat.KeepWithNext = $true
    }
    $doc.Styles.Item('Heading 1').Font.Size = 16
    $doc.Styles.Item('Heading 1').ParagraphFormat.SpaceBefore = 18
    $doc.Styles.Item('Heading 1').ParagraphFormat.SpaceAfter = 8
    $doc.Styles.Item('Heading 2').Font.Size = 13
    $doc.Styles.Item('Heading 2').ParagraphFormat.SpaceBefore = 14
    $doc.Styles.Item('Heading 2').ParagraphFormat.SpaceAfter = 7
    $doc.Styles.Item('Heading 3').Font.Size = 11
    $doc.Styles.Item('Heading 3').ParagraphFormat.SpaceBefore = 10
    $doc.Styles.Item('Heading 3').ParagraphFormat.SpaceAfter = 5

    $header = $doc.Sections.Item(1).Headers.Item(1).Range
    $header.Text = 'EN-IDP  |  SYSTEM TEST CHECKLIST'
    $header.Font.Name = 'Tahoma'
    $header.Font.Size = 8
    $header.Font.Bold = $true
    $header.Font.Color = $midGray
    $header.ParagraphFormat.Alignment = $wdAlignParagraphLeft
    $header.ParagraphFormat.Borders.Item(-3).Color = $border
    $header.ParagraphFormat.Borders.Item(-3).LineWidth = 4

    $footer = $doc.Sections.Item(1).Footers.Item(1).Range
    $footer.Text = 'Faculty of Engineering, Khon Kaen University  |  '
    $footer.Font.Name = 'Tahoma'
    $footer.Font.Size = 8
    $footer.Font.Color = $midGray
    $footer.ParagraphFormat.Alignment = $wdAlignParagraphRight
    $footer.Collapse($wdCollapseEnd)
    $doc.Fields.Add($footer, $wdFieldPage) | Out-Null
    $footer.InsertAfter(' / ')
    $footer.Collapse($wdCollapseEnd)
    $doc.Fields.Add($footer, $wdFieldNumPages) | Out-Null

    if (Test-Path $logoPath) {
        $logo = $selection.InlineShapes.AddPicture($logoPath)
        $logo.LockAspectRatio = -1
        $logo.Width = $word.InchesToPoints(1.75)
        $selection.ParagraphFormat.Alignment = $wdAlignParagraphLeft
        $selection.TypeParagraph()
    }

    $selection.Style = 'Title'
    $selection.TypeText('แผนและรายการตรวจสอบการทดสอบระบบ')
    $selection.TypeParagraph()
    $selection.Style = 'Subtitle'
    $selection.TypeText('EN-IDP Competency & Individual Development Plan Management System')
    $selection.TypeParagraph()

    $selection.Font.Name = 'Tahoma'
    $selection.Font.Size = 10
    $selection.Font.Bold = $true
    $selection.Font.Color = $red
    $selection.TypeText('SYSTEM TEST CHECKLIST  |  ฉบับใช้งานทดสอบระบบครบวงจร')
    $selection.TypeParagraph()
    $selection.Font.Bold = $false
    $selection.Font.Color = $midGray
    $selection.TypeText('จัดทำจากขอบเขตระบบและชุดทดสอบใน repository ณ วันที่ 25 กันยายน 2569')
    $selection.TypeParagraph()
    $selection.TypeParagraph()

    $meta = $doc.Tables.Add($selection.Range, 6, 2)
    $meta.AllowAutoFit = $false
    $meta.Columns.Item(1).Width = $word.InchesToPoints(1.55)
    $meta.Columns.Item(2).Width = $word.InchesToPoints(4.95)
    $meta.Borders.Enable = 1
    $meta.TopPadding = 6; $meta.BottomPadding = 6; $meta.LeftPadding = 8; $meta.RightPadding = 8
    $metaData = @(
        @('โครงการ', 'ระบบบริหารสมรรถนะและแผนพัฒนารายบุคคล (EN-IDP)'),
        @('เอกสารเลขที่', 'EN-IDP-QA-001'),
        @('เวอร์ชัน', '1.0'),
        @('สภาพแวดล้อม', '□ Local   □ Test/UAT   □ Production-like'),
        @('ผู้ทดสอบ / วันที่', '____________________________________________'),
        @('ผลรวม', '□ ผ่าน   □ ผ่านแบบมีเงื่อนไข   □ ไม่ผ่าน')
    )
    for ($i = 1; $i -le $metaData.Count; $i++) {
        $meta.Cell($i, 1).Range.Text = $metaData[$i - 1][0]
        $meta.Cell($i, 2).Range.Text = $metaData[$i - 1][1]
        $meta.Cell($i, 1).Shading.BackgroundPatternColor = $lightRed
        $meta.Cell($i, 1).Range.Font.Bold = $true
        foreach ($c in 1..2) {
            $meta.Cell($i, $c).VerticalAlignment = $wdCellAlignVerticalCenter
            $meta.Cell($i, $c).Range.Font.Name = 'Tahoma'
            $meta.Cell($i, $c).Range.Font.Size = 9
            $meta.Cell($i, $c).Range.ParagraphFormat.SpaceAfter = 0
        }
    }
    $selection.SetRange($doc.Content.End - 1, $doc.Content.End - 1)
    $selection.TypeParagraph()
    $selection.Style = 'Heading 2'
    $selection.TypeText('วัตถุประสงค์')
    $selection.TypeParagraph()
    $selection.Style = 'Normal'
    $selection.TypeText('ใช้เป็น checklist สำหรับ System Test, UAT และ Regression Test ครอบคลุมกระบวนการตั้งค่าระบบ การประเมินสมรรถนะ การอนุมัติหลายลำดับ Competency Gap แผน IDP การติดตามผล รายงาน และข้อกำหนดด้านสิทธิ์/ความถูกต้องของข้อมูล')
    $selection.TypeParagraph()
    $selection.InsertBreak($wdPageBreak)

    $selection.Style = 'Heading 1'
    $selection.TypeText('สารบัญ')
    $selection.TypeParagraph()
    $tocRange = $selection.Range
    $toc = $doc.TablesOfContents.Add($tocRange, $true, 1, 2)
    $selection.SetRange($doc.Content.End - 1, $doc.Content.End - 1)
    $selection.InsertBreak($wdPageBreak)

    $selection.Style = 'Heading 1'
    $selection.TypeText('คำแนะนำการใช้งานเอกสาร')
    $selection.TypeParagraph()
    $selection.Style = 'Normal'
    $selection.TypeText('ให้ทดสอบตามลำดับจาก ENV ไปยัง NFR เนื่องจากหลายกรณีต้องใช้ข้อมูลจากขั้นก่อนหน้า บันทึกผลเป็น P (Pass), F (Fail) หรือ N/A และระบุหมายเลข Defect ในช่องหมายเหตุหรือ Defect Log ท้ายเอกสาร')
    $selection.TypeParagraph()

    $selection.Style = 'Heading 2'
    $selection.TypeText('เกณฑ์ก่อนเริ่มทดสอบ')
    $selection.TypeParagraph()
    $preconditions = @(
        'ฐานข้อมูล PostgreSQL สำหรับทดสอบถูกสำรองก่อนเริ่ม และ migration เป็นสถานะ Ran ครบ',
        'มีบัญชีทดสอบอย่างน้อยหนึ่งบัญชีต่อบทบาท พร้อม assessment chain และ IDP chain',
        'มีโครงสร้างสายวิชาการและสายสนับสนุน รวมตำแหน่ง/ระดับที่ผูกผู้ใช้ได้จริง',
        'มีรอบประเมิน active, competency mapping, Expected Level และ FC rule สำหรับกรณีทดสอบ',
        'ตั้งค่า mail เป็น test transport หรือปิดการส่งจริง และเตรียมไฟล์หลักฐานที่อนุญาต/ไม่อนุญาต',
        'เปิด Developer Tools เพื่อเก็บ Network/Console evidence เมื่อพบปัญหา'
    )
    foreach ($item in $preconditions) {
        $selection.Style = 'Normal'
        $selection.TypeText('☐ ' + $item)
        $selection.TypeParagraph()
    }

    $selection.Style = 'Heading 2'
    $selection.TypeText('Test Data ที่แนะนำ')
    $selection.TypeParagraph()
    $selection.Style = 'Normal'
    $selection.TypeText('ใช้รหัสนำหน้า UAT- สำหรับข้อมูลชั่วคราว เช่น UAT-EMP-01, UAT-POS-01 และ UAT-COMP-01 เพื่อค้นหาและล้างข้อมูลได้ง่าย ห้ามใช้ข้อมูลบุคลากรจริงหรือส่งอีเมลจริงระหว่างการทดสอบโดยไม่ได้รับอนุญาต')
    $selection.TypeParagraph()

    foreach ($section in $sections) {
        $selection.InsertBreak($wdPageBreak)
        $selection.Style = 'Heading 1'
        $selection.TypeText($section.Code + '  ' + $section.Title)
        $selection.TypeParagraph()
        $selection.Style = 'Normal'
        $selection.Font.Size = 9
        $selection.Font.Color = $midGray
        $selection.TypeText('ผู้รับผิดชอบหลัก: ' + $section.Owner + '  |  จำนวนกรณีทดสอบ: ' + $section.Cases.Count)
        $selection.TypeParagraph()

        $table = $doc.Tables.Add($selection.Range, $section.Cases.Count + 1, 4)
        $table.AllowAutoFit = $false
        $table.Columns.Item(1).Width = $word.InchesToPoints(0.78)
        $table.Columns.Item(2).Width = $word.InchesToPoints(2.75)
        $table.Columns.Item(3).Width = $word.InchesToPoints(2.12)
        $table.Columns.Item(4).Width = $word.InchesToPoints(0.85)
        $table.Borders.Enable = 1
        $table.Borders.OutsideColor = $border
        $table.Borders.InsideColor = $border
        $table.TopPadding = 5; $table.BottomPadding = 5; $table.LeftPadding = 6; $table.RightPadding = 6
        $table.Rows.Item(1).HeadingFormat = -1
        $headers = @('Test ID', 'รายการตรวจสอบ / ขั้นตอน', 'ผลที่คาดหวัง', 'ผล')
        for ($col = 1; $col -le 4; $col++) {
            $cell = $table.Cell(1, $col)
            $cell.Range.Text = $headers[$col - 1]
            $cell.Shading.BackgroundPatternColor = $red
            $cell.Range.Font.Color = $white
            $cell.Range.Font.Bold = $true
            $cell.Range.Font.Name = 'Tahoma'
            $cell.Range.Font.Size = 8.5
            $cell.Range.ParagraphFormat.Alignment = $wdAlignParagraphCenter
            $cell.Range.ParagraphFormat.SpaceAfter = 0
            $cell.VerticalAlignment = $wdCellAlignVerticalCenter
        }
        for ($row = 2; $row -le $section.Cases.Count + 1; $row++) {
            $case = $section.Cases[$row - 2]
            $values = @($case.Id, $case.Check, $case.Expected, '☐ P`n☐ F`n☐ N/A')
            for ($col = 1; $col -le 4; $col++) {
                $cell = $table.Cell($row, $col)
                $cell.Range.Text = $values[$col - 1]
                $cell.Range.Font.Name = 'Tahoma'
                $cell.Range.Font.Size = if ($col -eq 4) { 7.5 } else { 8.2 }
                $cell.Range.Font.Color = $navy
                $cell.Range.ParagraphFormat.SpaceAfter = 0
                $cell.Range.ParagraphFormat.LineSpacingRule = 0
                $cell.VerticalAlignment = $wdCellAlignVerticalCenter
                if ($col -eq 1 -or $col -eq 4) {
                    $cell.Range.ParagraphFormat.Alignment = $wdAlignParagraphCenter
                }
            }
            if (($row % 2) -eq 1) {
                for ($col = 1; $col -le 4; $col++) { $table.Cell($row, $col).Shading.BackgroundPatternColor = $lightGray }
            }
        }
        $selection.SetRange($doc.Content.End - 1, $doc.Content.End - 1)
        $selection.TypeParagraph()
        $selection.Font.Name = 'Tahoma'
        $selection.Font.Size = 8
        $selection.Font.Color = $midGray
        $selection.TypeText('หมายเหตุ / Defect ID: __________________________________________________________________________________')
        $selection.TypeParagraph()
    }

    $selection.InsertBreak($wdPageBreak)
    $selection.Style = 'Heading 1'
    $selection.TypeText('แผนที่อ้างอิงชุดทดสอบอัตโนมัติ')
    $selection.TypeParagraph()
    $selection.Style = 'Normal'
    $selection.TypeText('รายการต่อไปนี้ช่วยเลือก focused test ก่อนรัน full regression ทั้งชุด โดย manual checklist ยังคงจำเป็นสำหรับ layout, interaction, email rendering และ workflow ข้ามบทบาท')
    $selection.TypeParagraph()

    $autoTable = $doc.Tables.Add($selection.Range, $automationMap.Count + 1, 2)
    $autoTable.AllowAutoFit = $false
    $autoTable.Columns.Item(1).Width = $word.InchesToPoints(2.35)
    $autoTable.Columns.Item(2).Width = $word.InchesToPoints(4.15)
    $autoTable.Borders.Enable = 1
    $autoTable.TopPadding = 6; $autoTable.BottomPadding = 6; $autoTable.LeftPadding = 7; $autoTable.RightPadding = 7
    $autoTable.Cell(1, 1).Range.Text = 'ขอบเขต'
    $autoTable.Cell(1, 2).Range.Text = 'Feature Test ที่เกี่ยวข้อง'
    foreach ($col in 1..2) {
        $autoTable.Cell(1, $col).Shading.BackgroundPatternColor = $red
        $autoTable.Cell(1, $col).Range.Font.Color = $white
        $autoTable.Cell(1, $col).Range.Font.Bold = $true
        $autoTable.Cell(1, $col).Range.ParagraphFormat.Alignment = $wdAlignParagraphCenter
    }
    for ($i = 0; $i -lt $automationMap.Count; $i++) {
        $autoTable.Cell($i + 2, 1).Range.Text = $automationMap[$i][0]
        $autoTable.Cell($i + 2, 2).Range.Text = $automationMap[$i][1]
    }
    foreach ($cell in $autoTable.Range.Cells) {
        $cell.Range.Font.Name = 'Tahoma'
        $cell.Range.Font.Size = 8.5
        $cell.Range.ParagraphFormat.SpaceAfter = 0
        $cell.VerticalAlignment = $wdCellAlignVerticalCenter
    }
    $selection.SetRange($doc.Content.End - 1, $doc.Content.End - 1)
    $selection.TypeParagraph()
    $selection.Style = 'Heading 2'
    $selection.TypeText('คำสั่งตรวจสอบมาตรฐาน')
    $selection.TypeParagraph()
    $selection.Style = 'Normal'
    $selection.Font.Name = 'Consolas'
    $selection.Font.Size = 9
    $selection.TypeText("php artisan test`n" + "npm run build`n" + "php artisan migrate:status")
    $selection.TypeParagraph()

    $selection.InsertBreak($wdPageBreak)
    $selection.Style = 'Heading 1'
    $selection.TypeText('Defect Log')
    $selection.TypeParagraph()
    $defect = $doc.Tables.Add($selection.Range, 13, 6)
    $defect.AllowAutoFit = $false
    $widths = @(0.65, 0.7, 2.05, 0.7, 1.25, 1.15)
    for ($i = 1; $i -le 6; $i++) { $defect.Columns.Item($i).Width = $word.InchesToPoints($widths[$i - 1]) }
    $defect.Borders.Enable = 1
    $defect.TopPadding = 8; $defect.BottomPadding = 8; $defect.LeftPadding = 5; $defect.RightPadding = 5
    $defectHeaders = @('No.', 'Test ID', 'รายละเอียดปัญหา', 'ระดับ', 'ผู้รับผิดชอบ', 'สถานะ/วันที่')
    for ($col = 1; $col -le 6; $col++) {
        $defect.Cell(1, $col).Range.Text = $defectHeaders[$col - 1]
        $defect.Cell(1, $col).Shading.BackgroundPatternColor = $red
        $defect.Cell(1, $col).Range.Font.Color = $white
        $defect.Cell(1, $col).Range.Font.Bold = $true
    }
    for ($row = 2; $row -le 13; $row++) { $defect.Cell($row, 1).Range.Text = [string]($row - 1) }
    foreach ($cell in $defect.Range.Cells) {
        $cell.Range.Font.Name = 'Tahoma'
        $cell.Range.Font.Size = 8
        $cell.Range.ParagraphFormat.SpaceAfter = 0
        $cell.VerticalAlignment = $wdCellAlignVerticalCenter
    }

    $selection.SetRange($doc.Content.End - 1, $doc.Content.End - 1)
    $selection.TypeParagraph()
    $selection.Style = 'Heading 1'
    $selection.TypeText('สรุปผลและ Sign-off')
    $selection.TypeParagraph()
    $summary = $doc.Tables.Add($selection.Range, 7, 4)
    $summary.AllowAutoFit = $false
    foreach ($i in 1..4) { $summary.Columns.Item($i).Width = $word.InchesToPoints(@(2.0, 1.25, 2.0, 1.25)[$i - 1]) }
    $summary.Borders.Enable = 1
    $summary.TopPadding = 7; $summary.BottomPadding = 7; $summary.LeftPadding = 7; $summary.RightPadding = 7
    $summaryData = @(
        @('จำนวน Test Case ทั้งหมด', ($sections | ForEach-Object { $_.Cases.Count } | Measure-Object -Sum).Sum, 'Pass', '________'),
        @('Fail', '________', 'Blocked', '________'),
        @('N/A', '________', 'Defect เปิดอยู่', '________'),
        @('ผู้ทดสอบ', '________________', 'วันที่', '____/____/____'),
        @('ผู้ตรวจสอบ', '________________', 'วันที่', '____/____/____'),
        @('ผู้อนุมัติ UAT', '________________', 'วันที่', '____/____/____'),
        @('ข้อสรุป', '□ ผ่าน  □ ผ่านแบบมีเงื่อนไข  □ ไม่ผ่าน', 'หมายเหตุ', '________________')
    )
    for ($row = 1; $row -le $summaryData.Count; $row++) {
        for ($col = 1; $col -le 4; $col++) {
            $summary.Cell($row, $col).Range.Text = [string]$summaryData[$row - 1][$col - 1]
            $summary.Cell($row, $col).Range.Font.Name = 'Tahoma'
            $summary.Cell($row, $col).Range.Font.Size = 8.5
            $summary.Cell($row, $col).Range.ParagraphFormat.SpaceAfter = 0
            $summary.Cell($row, $col).VerticalAlignment = $wdCellAlignVerticalCenter
            if ($col -eq 1 -or $col -eq 3) {
                $summary.Cell($row, $col).Shading.BackgroundPatternColor = $lightRed
                $summary.Cell($row, $col).Range.Font.Bold = $true
            }
        }
    }

    foreach ($table in $doc.Tables) {
        $table.Rows.AllowBreakAcrossPages = 0
    }

    $doc.TablesOfContents.Item(1).Update()
    $doc.Fields.Update() | Out-Null
    $doc.Repaginate()
    $doc.BuiltInDocumentProperties.Item('Title').Value = 'EN-IDP System Test Checklist'
    $doc.BuiltInDocumentProperties.Item('Subject').Value = 'System Test, UAT and Regression Checklist'
    $doc.BuiltInDocumentProperties.Item('Author').Value = 'EN-IDP Project Team'
    $doc.SaveAs2($docxPath, 16)
    $doc.ExportAsFixedFormat(
        $pdfPath,
        $wdExportFormatPDF,
        $false,
        $wdExportOptimizeForPrint,
        $wdExportAllDocument,
        1,
        1,
        $wdExportDocumentContent,
        $true,
        $true,
        $wdExportCreateHeadingBookmarks,
        $true,
        $true,
        $false
    )

    $pageCount = $doc.ComputeStatistics(2)
    $powerPoint = New-Object -ComObject PowerPoint.Application
    $presentation = $powerPoint.Presentations.Add()
    $presentation.PageSetup.SlideWidth = 612
    $presentation.PageSetup.SlideHeight = 792

    for ($pageNumber = 1; $pageNumber -le $pageCount; $pageNumber++) {
        $start = $doc.GoTo($wdGoToPage, $wdGoToAbsolute, $pageNumber).Start
        if ($pageNumber -lt $pageCount) {
            $finish = $doc.GoTo($wdGoToPage, $wdGoToAbsolute, $pageNumber + 1).Start - 1
        } else {
            $finish = $doc.Content.End - 1
        }
        $pageRange = $doc.Range($start, $finish)
        $pageRange.CopyAsPicture()
        $slide = $presentation.Slides.Add($presentation.Slides.Count + 1, $ppLayoutBlank)
        $shapeRange = $slide.Shapes.PasteSpecial($wdPasteEnhancedMetafile)
        $shape = $shapeRange.Item(1)
        $shape.LockAspectRatio = -1
        $scale = [Math]::Min($presentation.PageSetup.SlideWidth / $shape.Width, $presentation.PageSetup.SlideHeight / $shape.Height)
        $shape.Width = $shape.Width * $scale
        $shape.Height = $shape.Height * $scale
        $shape.Left = ($presentation.PageSetup.SlideWidth - $shape.Width) / 2
        $shape.Top = ($presentation.PageSetup.SlideHeight - $shape.Height) / 2
        $slide.Export((Join-Path $previewDirectory ('page-{0:D2}.png' -f $pageNumber)), 'PNG', 1224, 1584)
    }

    [pscustomobject]@{
        Docx = $docxPath
        Pdf = $pdfPath
        Preview = $previewDirectory
        Pages = $pageCount
        TestCases = ($sections | ForEach-Object { $_.Cases.Count } | Measure-Object -Sum).Sum
    } | ConvertTo-Json
}
finally {
    if ($presentation) { $presentation.Close() }
    if ($powerPoint) { $powerPoint.Quit() }
    if ($doc) { $doc.Close($false) }
    if ($word) { $word.Quit() }
    foreach ($comObject in @($presentation, $powerPoint, $doc, $word)) {
        if ($comObject) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($comObject) }
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
