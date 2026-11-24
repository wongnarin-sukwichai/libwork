// สีสถานะชุดเดียว ใช้ทั้ง badge (StatusPill) และการ์ดสถิติหน้าแรก HR ให้สีตรงกัน
export const STATUS_TONES = {
    ok: 'bg-green-50 text-green-800',
    late: 'bg-orange-100 text-orange-900', // สาย/ออกก่อน (เพื่อทราบ) เข้มกว่าแดงอ่อนหนึ่งระดับให้แยกออก
    bad: 'bg-red-50 text-red-800', // ต้องแก้ เช่น ไม่ได้ลงเวลาออก
    absent: 'bg-red-700 text-white', // ไม่ได้ลงเวลาเลย (รุนแรงสุด)
    leave: 'bg-soft-2 text-navy-700',
    duty: 'bg-sun-100 text-sun-800',
    ot: 'bg-teal-50 text-teal-800', // OT ปกติ
    special: 'bg-violet-100 text-violet-900', // ล่วงเวลาพิเศษ (ม่วง = สีประจำล่วงเวลาพิเศษ)
    neutral: 'bg-[#F2F4F7] text-[#52606D]',
};
