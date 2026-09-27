# IDP Plan and Approval Design

## Objective

ปรับโครงสร้างแผนพัฒนารายบุคคลให้หนึ่งสมรรถนะที่มี Competency Gap ติดลบสร้างได้หนึ่งแผน และแต่ละแผนมีหลายกิจกรรม พร้อม workflow อนุมัติแยกทีละสมรรถนะตาม `users.supervisor_id_1` ถึง `users.supervisor_id_3`

## Data Model

### `idps`

เก็บหัวเอกสารแผนพัฒนารายบุคคลของผู้ใช้ในปีหรือรอบประเมินหนึ่งรอบ

- หนึ่งผู้ใช้มีหนึ่ง `idps` ต่อปีหรือรอบประเมิน
- เก็บสถานะภาพรวมซึ่งคำนวณจากสถานะของ `idp_items`
- ไม่เก็บ `supervisor_id_1` ถึง `supervisor_id_3` ซ้ำ เพราะใช้ข้อมูลล่าสุดจาก `users`

### `idp_items`

เก็บแผนพัฒนารายสมรรถนะ

- หนึ่งแถวต่อหนึ่ง `competency_gap_id`
- `goal` และ `success_criteria` กรอกหนึ่งครั้งต่อสมรรถนะ
- พฤติกรรมบ่งชี้ที่เป็น Gap ไม่ถูกสร้างเป็น `idp_items`
- บังคับ unique constraint ที่ `idp_id + competency_gap_id`

คอลัมน์ workflow ที่ต้องใช้:

- `status`: `draft`, `pending`, `revision_required`, `approved`
- `current_review_step`: `1`, `2`, `3` หรือ `null`
- `submitted_at`
- `approved_at`
- `rejected_at`

### `idp_activities`

เก็บกิจกรรมพัฒนาหลายรายการภายใต้ `idp_items` หนึ่งรายการ

- กิจกรรมที่กรอกเองหรือเลือกจาก Learning Catalog
- ประเภทการเรียนรู้
- เครื่องมือหรือแนวทางการพัฒนา
- น้ำหนัก
- รายละเอียดกิจกรรม
- วันที่เริ่มต้นและสิ้นสุด

น้ำหนักรวมของกิจกรรมใน `idp_item` เดียวกันต้องเท่ากับ 100% ก่อนส่งอนุมัติ

### `idp_activity_updates`

เก็บความก้าวหน้าของกิจกรรมแต่ละรายการ เช่น เปอร์เซ็นต์ความคืบหน้า บันทึกผล และหลักฐานประกอบเมื่อเปิดใช้ในอนาคต

### `idp_item_approvals`

เก็บประวัติการตัดสินใจของผู้ประเมินแยกตามสมรรถนะ

คอลัมน์:

- `id`
- `idp_item_id`
- `review_step`
- `reviewer_id`
- `status`: `approved` หรือ `rejected`
- `comment`
- `reviewed_at`
- timestamps

ไม่สร้างแถว `pending` ล่วงหน้า สถานะรอและผู้ตรวจปัจจุบันคำนวณจาก `idp_items.current_review_step` และ `users.supervisor_id_{step}`

## Missing Behaviors

พฤติกรรมบ่งชี้ที่ไม่ผ่านดึงจากผลการประเมินเดิมผ่าน `competency_gap_id`

- แสดงในหน้า IDP แบบ read-only
- ผู้ใช้แก้ไขหรือลบไม่ได้
- ไม่คัดลอก `behavior_key` หรือ `behavior_description` ไปเก็บซ้ำใน `idp_items`
- ผลการประเมินเป็นแหล่งข้อมูลหลักเพียงแห่งเดียว

## Submission Workflow

เมื่อผู้ใช้ส่ง `idp_item`:

1. ตรวจว่า `goal` และ `success_criteria` ครบ
2. ตรวจว่ามีกิจกรรมอย่างน้อยหนึ่งรายการ
3. ตรวจข้อมูลบังคับของทุกกิจกรรม
4. ตรวจว่าน้ำหนักรวมเท่ากับ 100%
5. อ่าน `supervisor_id_1` ถึง `supervisor_id_3` จากเจ้าของแผน
6. เลือกลำดับแรกที่มีผู้ตรวจ โดยข้ามช่องที่เป็น `null`
7. ตั้ง `status = pending` และ `current_review_step` เป็นลำดับนั้น
8. หากไม่มีผู้ตรวจเลย ให้ปิดรายการเป็น `approved`

แต่ละ `idp_item` ส่งและอนุมัติแยกจากรายการสมรรถนะอื่นได้

## Approval Workflow

หัวหน้ามีสิทธิ์ตรวจเมื่อ:

```text
idp_items.status = pending
idp_items.current_review_step = N
users.supervisor_id_N ของเจ้าของแผน = ผู้ใช้ที่ login
```

เมื่ออนุมัติ:

1. บันทึก `approved` ลง `idp_item_approvals`
2. หาลำดับถัดไปจาก `users.supervisor_id_1` ถึง `supervisor_id_3`
3. ข้ามลำดับที่ไม่มีผู้ตรวจ
4. ถ้ามีลำดับถัดไป ให้อัปเดต `current_review_step`
5. ถ้าไม่มีลำดับถัดไป ให้ตั้ง `idp_items.status = approved`, ล้าง `current_review_step` และบันทึก `approved_at`

เมื่อปฏิเสธ:

1. บังคับให้กรอก comment
2. บันทึก `rejected` ลง `idp_item_approvals`
3. ตั้ง `idp_items.status = revision_required`
4. ล้าง `current_review_step`
5. เปิดให้เจ้าของแผนแก้ `goal`, `success_criteria` และกิจกรรมทั้งหมดของสมรรถนะนั้น
6. สมรรถนะอื่นไม่ถูกเปลี่ยนสถานะ

เมื่อผู้ใช้ส่งรายการที่ถูกปฏิเสธใหม่ ระบบเริ่มเส้นทางอนุมัติตั้งแต่ลำดับแรกอีกครั้ง

## Supervisor Changes

เส้นทางที่ยังรออนุมัติใช้ข้อมูล supervisor ล่าสุดจาก `users`

- หาก Admin เปลี่ยนผู้ตรวจของขั้นปัจจุบัน รายการรอจะปรากฏแก่ผู้ตรวจคนใหม่ทันที
- ขั้นที่อนุมัติแล้วไม่ต้องอนุมัติซ้ำ
- การหาขั้นถัดไปต้องข้ามขั้นที่มีประวัติ `approved` ของรอบการส่งปัจจุบัน
- ถ้า Admin ลบผู้ตรวจของขั้นปัจจุบัน ระบบหาขั้นถัดไปที่มีผู้ตรวจ
- ถ้าไม่มีขั้นถัดไป ระบบปิดรายการเป็น `approved`

เพื่อแยกรอบการส่งหลังถูกปฏิเสธ ควรเพิ่ม `submission_version` ใน `idp_items` และ `idp_item_approvals` ทุกครั้งที่ผู้ใช้ส่งใหม่ให้เพิ่ม version หนึ่งค่า ประวัติ approval ของ version เก่ายังคงอยู่ แต่ไม่ใช้ตัดสิน workflow ปัจจุบัน

## Overall IDP Status

`idps.status` เป็นค่าภาพรวม:

- `draft`: ทุกรายการยังเป็น draft
- `in_progress`: มีหลายสถานะผสมกัน หรือมีรายการกำลังรอ/ถูกส่งกลับ
- `submitted`: ทุกรายการส่งแล้ว แต่ยังอนุมัติไม่ครบ
- `approved`: `idp_items` ทุกแถวได้รับอนุมัติแล้ว

ค่าภาพรวมต้องคำนวณใหม่หลังบันทึกร่าง ส่ง อนุมัติ หรือปฏิเสธรายการใดรายการหนึ่ง

## Head and Manager Views

หน้าหัวหน้าแบ่งข้อมูลเป็นสองมุมมอง:

1. รายการรอฉันอนุมัติ
   - ใช้ `current_review_step` เทียบกับ `users.supervisor_id_{step}`
   - แสดงเฉพาะ `idp_items` ที่อยู่ในขั้นของผู้ใช้ที่ login
2. IDP ของลูกน้อง
   - แสดงลูกน้องที่มีผู้ใช้ login อยู่ใน `supervisor_id_1`, `supervisor_id_2` หรือ `supervisor_id_3`
   - แสดงสถานะรายสมรรถนะและสถานะภาพรวม
   - เปิดดูเป้าหมาย KPI กิจกรรม น้ำหนัก ความคืบหน้า และประวัติ comment ได้

## Validation and Authorization

- เจ้าของแผนแก้ได้เฉพาะ `draft` และ `revision_required`
- ผู้ตรวจอนุมัติได้เฉพาะรายการที่มาถึงลำดับของตน
- ผู้ตรวจห้ามอนุมัติซ้ำใน version เดียวกัน
- ปฏิเสธต้องมี comment แต่อนุมัติจะมีหรือไม่มี comment ก็ได้
- `competency_gap_id` ต้องเป็น Gap ติดลบที่อนุมัติแล้วของเจ้าของแผน
- ห้ามสร้าง `idp_items` ซ้ำภายใน `idps` เดียวกัน

## Migration Strategy

ข้อมูลเดิมที่สร้างหนึ่ง `idp_items` ต่อหนึ่งพฤติกรรมต้องรวมตาม:

```text
idp_id + competency_gap_id
```

สำหรับแต่ละกลุ่ม:

1. เลือกหนึ่ง `idp_items` เป็นแผนหลัก
2. รวม `idp_activities` เดิมทั้งหมดมาอยู่ใต้แผนหลัก
3. เลือก `goal` และ `success_criteria` ที่ไม่ว่างค่าแรก
4. ลบ `idp_items` ซ้ำหลังย้ายกิจกรรมแล้ว
5. เพิ่ม unique constraint
6. เลิกใช้และลบ `behavior_key` กับ `behavior_description`

การ migration ต้องทำใน transaction และมี test ยืนยันว่ากิจกรรมเดิมไม่สูญหาย

## Test Coverage

- หนึ่ง Gap สร้างได้หนึ่ง `idp_item`
- หนึ่ง `idp_item` มีหลายกิจกรรม
- ห้ามส่งเมื่อกิจกรรมไม่ครบหรือน้ำหนักไม่เท่ากับ 100%
- ส่งไป supervisor ขั้นแรกที่มีค่า
- ข้าม supervisor ที่เป็น `null`
- อนุมัติแล้วส่งต่อขั้นถัดไป
- ขั้นสุดท้ายอนุมัติแล้วปิดเป็น `approved`
- ปฏิเสธโดยไม่มี comment ไม่ได้
- ปฏิเสธแล้วแก้ได้เฉพาะสมรรถนะนั้น
- เปลี่ยน supervisor ระหว่างรอแล้วผู้ตรวจใหม่เห็นรายการ
- ขั้นที่อนุมัติแล้วไม่ต้องอนุมัติซ้ำหลังเปลี่ยน supervisor
- หัวหน้าเห็นรายการรออนุมัติและข้อมูลลูกน้องตามสายบังคับบัญชา
- migration รวมข้อมูลเก่าโดยไม่ทำกิจกรรมสูญหาย
