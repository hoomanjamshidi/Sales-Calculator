/** حساب روزهای تقویمی بدون تبدیل UTC؛ منطقهٔ زمانی مرورگر وارد قرارداد API نمی‌شود. */
import * as jalali from 'date-fns-jalali';
import * as gregorian from 'date-fns';
import type {Calendar} from './types';
export const digits=(value:string)=>value.replace(/[۰-۹٠-٩]/g,c=>String('۰۱۲۳۴۵۶۷۸۹'.includes(c)?'۰۱۲۳۴۵۶۷۸۹'.indexOf(c):'٠١٢٣٤٥٦٧٨٩'.indexOf(c)));
/** ساعت میانی روز مانع لغزش روز در تبدیل‌های محلی می‌شود. */
export function parseDay(value:string):Date {const [y,m,d]=value.split('-').map(Number);return new Date(y,m-1,d,12);}
/** قرارداد سرور همواره تاریخ میلادی بدون ساعت است. */
export function isoDay(date:Date):string {return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;}
/** انتخاب توابع تقویم در یک نقطه نگه داشته می‌شود. */
export function calendarFns(calendar:Calendar){return calendar==='jalali'?jalali:gregorian;}
/** نمایش تاریخ مطابق تقویم منتخب؛ مقدار ذخیره‌شده ثابت می‌ماند. */
export function displayDay(value:string,calendar:Calendar):string {return calendarFns(calendar).format(parseDay(value),'yyyy/MM/dd');}
/** ورودی دستی با بازتبدیل کنترل می‌شود تا روز نامعتبر پذیرفته نشود. */
export function parseInput(value:string,calendar:Calendar):string|null {
 const normalized=digits(value).replaceAll('-','/');
 if(!/^\d{4}\/\d{1,2}\/\d{1,2}$/.test(normalized))return null;
 const f=calendarFns(calendar); const date=f.parse(normalized,'yyyy/M/d',new Date(2024,0,1,12));
 if(!f.isValid(date))return null;
 const [y,m,d]=normalized.split('/').map(Number);
 if(f.getYear(date)!==y||f.getMonth(date)+1!==m||f.getDate(date)!==d)return null;
 return isoDay(date);
}
/** امروزِ سرور فقط اگر تاریخ میلادی معتبر باشد پذیرفته می‌شود؛ وگرنه (مثلاً خروجی شمسی‌شده) امروز مرورگر. */
export function safeToday(value:string|undefined):string {
 const text=digits(value||'');
 if(/^\d{4}-\d{2}-\d{2}$/.test(text)){const year=Number(text.slice(0,4));const date=parseDay(text);if(year>=1900&&year<=2200&&isoDay(date)===text)return text;}
 return isoDay(new Date());
}
/** پریست‌های روزی شامل امروز هستند؛ ماه و سال جاری تابع تقویم‌اند. */
export function presetRange(key:string,today:string,calendar:Calendar):[string,string] {
 const date=parseDay(today);const f=calendarFns(calendar);
 if(key==='yesterday'){const day=isoDay(gregorian.subDays(date,1));return [day,day];}
 if(key==='month')return [isoDay(f.startOfMonth(date)),today];
 if(key==='year')return [isoDay(f.startOfYear(date)),today];
 return [isoDay(gregorian.subDays(date,Math.max(1,Number(key)||1)-1)),today];
}
