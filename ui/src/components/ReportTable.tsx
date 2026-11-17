/** جدول گروهی محصولات با زبان بصری جدول داده‌ی طرحک: مرتب‌سازی سمت سرور، جست‌وجو و زیرردیف متغیرها. */
import {useState} from 'react';
import {AnimatePresence,MotionConfig,motion,useReducedMotion} from 'motion/react';
import {HugeiconsIcon} from '@hugeicons/react';
import {ArrowDown01Icon,ArrowLeft01Icon,ArrowRight01Icon,Cancel01Icon,PackageOpenIcon,Search01Icon} from '@hugeicons/core-free-icons';
import {cn} from '@/lib/utils';
import {SortIcon,listSpring} from '@/components/animated-data-table';
import type {Report,Row} from '../types';
const format=(n:string|number)=>new Intl.NumberFormat('fa-IR',{maximumFractionDigits:2}).format(Number(n)||0);
type SortKey='title'|'sold'|'returned'|'quantity'|'amount';
type Props={report:Report;sort:string;direction:string;search:string;onSearch:(value:string)=>void;onSort:(key:string)=>void;onPage:(page:number)=>void};
/** قالب ستون مشترک سربرگ و ردیف؛ در صفحهٔ باریک جدول افقی اسکرول می‌شود. */
const GRID='grid min-w-[640px] items-center gap-x-3 grid-cols-[minmax(12rem,2.4fr)_repeat(3,minmax(4.5rem,1fr))_minmax(7.5rem,1.3fr)]';
const COLUMNS:{key:SortKey;label:string}[]=[{key:'title',label:'محصول / متغیر'},{key:'sold',label:'فروخته‌شده'},{key:'returned',label:'مرجوع‌شده'},{key:'quantity',label:'تعداد خالص'},{key:'amount',label:'مبلغ خالص'}];
function Cells({row}:{row:Row}){
 return <>
  <span className="sc-tabular text-sm text-muted-foreground">{format(row.sold)}</span>
  <span className={cn('sc-tabular text-sm',Number(row.returned)>0?'text-destructive':'text-muted-foreground')}>{format(row.returned)}</span>
  <span className="sc-tabular text-sm text-foreground">{format(row.quantity)}</span>
  <span className="sc-tabular text-sm font-semibold text-foreground">{format(row.amount)}</span>
 </>;
}
export function ReportTable({report,sort,direction,search,onSearch,onSort,onPage}:Props){
 const reduce=useReducedMotion()??false;
 const [expanded,setExpanded]=useState<Record<string,boolean>>({});
 return <MotionConfig transition={listSpring}><div className="overflow-hidden border border-border bg-card shadow-[0_8px_35px_-20px_rgba(21,51,102,0.25)]" style={{borderRadius:20,borderWidth:1}}>
  <div className="flex flex-col gap-3 border-b border-border px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
   <div className="min-w-0 px-1">
    <h2 className="text-base font-semibold text-foreground">فروش محصولات</h2>
    <motion.p key={report.total} initial={reduce?false:{opacity:0,y:4}} animate={{opacity:1,y:0}} className="text-xs text-muted-foreground">{format(report.total)} محصول در این بازه · <span className="rounded-md border border-border bg-muted px-1.5 py-0.5 text-[11px]">{report.summary.unit||report.filters.currency}</span></motion.p>
   </div>
   <label className="relative flex min-w-0 items-center sm:w-[280px]">
    <span className="pointer-events-none absolute start-3 text-muted-foreground"><HugeiconsIcon icon={Search01Icon} size={16}/></span>
    <input type="search" value={search} onChange={e=>onSearch(e.target.value)} placeholder="جست‌وجو در نام محصول یا ویژگی‌ها…" aria-label="جست‌وجو در گزارش" className="h-10 w-full rounded-xl border border-border bg-background pe-9 ps-9 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"/>
    {search?<button type="button" aria-label="پاک کردن جست‌وجو" onClick={()=>onSearch('')} className="absolute end-2 flex size-6 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-foreground"><HugeiconsIcon icon={Cancel01Icon} size={14}/></button>:null}
   </label>
  </div>
  <div className="overflow-x-auto">
   <div className={cn(GRID,'border-b border-border bg-muted/50 px-4 py-2.5 text-xs text-muted-foreground')} role="row">
    {COLUMNS.map(col=>{const active=sort===col.key;return <button key={col.key} type="button" role="columnheader" aria-sort={active?(direction==='asc'?'ascending':'descending'):'none'} onClick={()=>onSort(col.key)} className={cn('inline-flex min-w-0 items-center gap-1 justify-self-start rounded-lg px-1 py-1 text-start transition-colors hover:bg-accent hover:text-foreground',active&&'text-foreground')}><span className="truncate">{col.label}</span><SortIcon active={active} dir={active?(direction==='asc'?'asc':'desc'):null}/></button>})}
   </div>
   <div className="relative min-h-[120px]" role="rowgroup">
    <AnimatePresence initial={false} mode="popLayout">
     {report.rows.length===0?<motion.div key="empty" initial={reduce?false:{opacity:0}} animate={{opacity:1}} exit={{opacity:0}} className="flex flex-col items-center gap-1.5 px-4 py-12 text-center">
      <span className="mb-1 flex size-12 items-center justify-center rounded-2xl bg-accent text-primary"><HugeiconsIcon icon={PackageOpenIcon} size={24}/></span>
      <strong className="text-sm text-foreground">فروشی برای این فیلتر پیدا نشد</strong>
      <span className="text-xs text-muted-foreground">{search?'عبارت جست‌وجو را تغییر دهید.':'بازه یا محصول دیگری را انتخاب کنید.'}</span>
     </motion.div>:report.rows.map((parent,index)=>{
      const children=(parent.children||[]).filter(c=>Number(c.variation_id)>0);
      const key=parent.product_id;const open=!!expanded[key];
      return <motion.div key={key} layout="position" initial={reduce?false:{opacity:0,y:10}} animate={{opacity:1,y:0}} exit={{opacity:0}} transition={{...listSpring,delay:reduce?0:Math.min(index,10)*0.025}} className="border-b border-border/70 last:border-b-0">
       <div className={cn(GRID,'px-4 py-3 transition-colors hover:bg-muted/40',open&&'bg-muted/30')} role="row">
        <div className="flex min-w-0 items-center gap-2.5">
         {children.length>0?<button type="button" aria-expanded={open} aria-label={`${open?'بستن':'نمایش'} متغیرهای ${parent.title}`} onClick={()=>setExpanded(v=>({...v,[key]:!v[key]}))} className={cn('flex size-8 shrink-0 items-center justify-center rounded-xl border border-border bg-background text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',open&&'border-primary/30 bg-accent text-primary')}><motion.span animate={{rotate:open?180:0}} className="flex"><HugeiconsIcon icon={ArrowDown01Icon} size={16}/></motion.span></button>:<span className="flex size-8 shrink-0 items-center justify-center"><span className="size-2 rounded-full bg-primary/40"/></span>}
         <div className="min-w-0"><p className="truncate text-sm font-medium text-foreground" title={parent.title}>{parent.title}{parent.deleted&&<span className="ms-1.5 inline-flex shrink-0 items-center rounded-full border border-destructive/20 bg-destructive/10 px-1.5 py-px align-middle text-[10px] font-medium text-destructive" title="این محصول از فروشگاه حذف شده است؛ فروش آن همچنان در محاسبه هست.">حذف‌شده</span>}</p>{children.length>0&&<p className="text-[11px] text-muted-foreground">{format(children.length)} متغیر</p>}</div>
        </div>
        <Cells row={parent}/>
       </div>
       <AnimatePresence initial={false}>
        {open&&<motion.div key="children" initial={reduce?false:{height:0,opacity:0}} animate={{height:'auto',opacity:1}} exit={{height:0,opacity:0}} className="overflow-hidden bg-muted/30">
         {children.map((item,i)=><div key={`${key}-${item.variation_id}-${i}`} className={cn(GRID,'border-t border-dashed border-border/70 px-4 py-2.5')} role="row">
          <div className="flex min-w-0 items-center gap-2.5 ps-5"><span className="h-3 w-3 shrink-0 rounded-bl-md border-b border-s border-border" aria-hidden/><p className="truncate text-[13px] text-muted-foreground" title={item.attributes||item.title}>{item.attributes||item.title}{item.deleted&&!parent.deleted&&<span className="ms-1.5 inline-flex shrink-0 items-center rounded-full border border-destructive/20 bg-destructive/10 px-1.5 py-px align-middle text-[10px] font-medium text-destructive" title="این محصول از فروشگاه حذف شده است؛ فروش آن همچنان در محاسبه هست.">حذف‌شده</span>}</p></div>
          <Cells row={item}/>
         </div>)}
        </motion.div>}
       </AnimatePresence>
      </motion.div>})}
    </AnimatePresence>
   </div>
  </div>
  <div className="flex items-center justify-between border-t border-border px-4 py-2.5 text-xs text-muted-foreground">
   <span>صفحهٔ {format(report.page)} از {format(report.pages)}</span>
   <div className="flex gap-1.5">
    <button type="button" aria-label="صفحهٔ قبل" disabled={report.page<=1} onClick={()=>onPage(report.page-1)} className="flex size-8 items-center justify-center rounded-xl border border-border bg-background text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-40"><HugeiconsIcon icon={ArrowRight01Icon} size={16}/></button>
    <button type="button" aria-label="صفحهٔ بعد" disabled={report.page>=report.pages} onClick={()=>onPage(report.page+1)} className="flex size-8 items-center justify-center rounded-xl border border-border bg-background text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-40"><HugeiconsIcon icon={ArrowLeft01Icon} size={16}/></button>
   </div>
  </div>
 </div></MotionConfig>;
}
