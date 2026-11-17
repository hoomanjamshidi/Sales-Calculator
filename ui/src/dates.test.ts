/** مرزهای تاریخ شمسی و پیش‌فرض‌های روزی بدون وابستگی به رابط آزموده می‌شوند. */
import {describe,expect,it} from 'vitest';
import {displayDay,isoDay,parseInput,presetRange,safeToday} from './dates';
describe('report date filters',()=>{
 it('converts Nowruz to the same ISO day',()=>{expect(parseInput('۱۴۰۵/۰۱/۰۱','jalali')).toBe('2026-03-21');expect(displayDay('2026-03-21','jalali')).toBe('1405/01/01')});
 it('rejects a Jalali-filtered server date as today',()=>{expect(safeToday('2026-10-22')).toBe('2026-10-22');expect(safeToday('1405-07-30')).toBe(isoDay(new Date()));expect(safeToday('۱۴۰۵-۰۷-۳۰')).toBe(isoDay(new Date()))});
 it('includes today in rolling presets',()=>{expect(presetRange('7','2026-10-21','jalali')).toEqual(['2026-10-15','2026-10-21'])});
 it('rejects impossible Gregorian date',()=>{expect(parseInput('2026/02/30','gregorian')).toBeNull()});
 it('accepts Jalali leap day and rejects a non-leap day',()=>{expect(parseInput('۱۳۹۹/۱۲/۳۰','jalali')).toBe('2021-03-20');expect(parseInput('۱۴۰۰/۱۲/۳۰','jalali')).toBeNull()});
 it('keeps the selected day when the display calendar changes',()=>{const day='2026-03-21';expect(parseInput(displayDay(day,'jalali'),'jalali')).toBe(day);expect(parseInput(displayDay(day,'gregorian'),'gregorian')).toBe(day)});
});
