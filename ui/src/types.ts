/** قرارداد تایپ‌شدهٔ رابط و API گزارش؛ مبالغ سرور برای حفظ دقت رشته هستند. */
export type Calendar = 'jalali' | 'gregorian';
export interface Filters { from:string; to:string; currency:string; products:number[]; categories:number[]; calendar:Calendar }
export interface Row { id?:string; product_id:string; variation_id?:string; title:string; attributes?:string; sold:string; returned:string; quantity:string; amount:string; deleted?:boolean; children?:Row[] }
export interface Report { id:string; state:'queued'|'ready'|'failed'; filters:Filters; summary:{quantity:string;amount:string;orders:number;unallocated:string;asOf:string;timezone:string;unit?:string;categories?:string[]};rows:Row[];total:number;page:number;pages:number;error:string }
export interface Status {ready:boolean;scanning:boolean;progress:{processed:number;total:number};syncedAt:string|null;changedAt:string|null;error:string|null;currencies:string[];today:string;timezone:string;pending:number}
export interface Brand {custom:boolean;name:string;nameEn:string;tagline:string;logo:string;mark:string;yarn:boolean}
export interface Config {api:string;nonce:string;store:string;today:string;currency:string;fonts:string;brand?:Brand}
declare global {interface Window {scConfig:Config}}
