/** نقطهٔ ورود رابط افزونه؛ وضعیت فیلتر، گزارش و دانلود را به کامپوننت‌های طرحک متصل می‌کند. */
import {createRoot} from 'react-dom/client';
import {useCallback,useEffect,useMemo,useRef,useState} from 'react';
import {motion,MotionConfig} from 'motion/react';
import {HugeiconsIcon,type IconSvgElement} from '@hugeicons/react';
import {Analytics01Icon,Calendar03Icon,ChartIncreaseIcon,Coins01Icon,FilterIcon,GlobalIcon,Invoice03Icon,Pdf02Icon,RefreshIcon,ShoppingBasket01Icon,Store01Icon,Xls02Icon} from '@hugeicons/core-free-icons';
import {api} from './api';
import DateField from './components/date-field';
import DiscreteTabs from './components/discrete-tabs';
import {SoftSelect,type SelectOption} from './components/animated-select';
import {NumberTicker} from './components/number-ticker';
import {SaveButton as StatusButton} from './components/save-button';
import {StatusMark} from './components/status-mark';
import {ToastViewport,useToasts} from './components/toast-stack';
import {ProductPicker} from './components/ProductPicker';
import {ReportTable} from './components/ReportTable';
import {isoDay,parseDay,presetRange,safeToday} from './dates';
import type {Calendar,Filters,Report,Status} from './types';
import './style.css';
type Product={id:string;name:string};
const format=(value:string|number)=>new Intl.NumberFormat('fa-IR',{maximumFractionDigits:2}).format(Number(value)||0);
// مبالغ ریالی هنگام ساخت گزارش به تومان تبدیل می‌شوند.
const CURRENCY_LABELS:Record<string,string>={IRR:'ریال (نمایش به تومان)',IRT:'تومان'};
const wait=(ms:number)=>new Promise(r=>setTimeout(r,ms));
const CALENDARS=[{id:'jalali',title:'شمسی',icon:Calendar03Icon},{id:'gregorian',title:'میلادی',icon:GlobalIcon}] as const;
const PRESETS:SelectOption[]=[['1','امروز'],['yesterday','دیروز'],['7','۷ روز اخیر'],['30','۳۰ روز اخیر'],['90','۹۰ روز اخیر'],['365','یک سال اخیر'],['month','ماه جاری'],['year','سال جاری'],['custom','بازهٔ دلخواه']].map(([value,label])=>({value,label}));
/** گزارش در صف ساخته می‌شود؛ تا آماده‌شدن نسخهٔ ثابت منتظر می‌مانیم. */
async function waitForReport(id:string,query:string):Promise<Report>{
 for(let tries=0;tries<200;tries++){
  const result=await api<Report>(`reports/${id}?${query}`);
  if(result.state==='ready')return result;
  if(result.state==='failed')throw new Error(result.error||'ساخت گزارش کامل نشد.');
  await wait(1500);
 }
 throw new Error('ساخت گزارش طولانی شده است؛ وضعیت صف ووکامرس را بررسی کنید.');
}
/** ایجاد خروجی و انتظار برای آماده‌شدن؛ لینک خصوصی فقط بعد از اتمام دریافت می‌شود. */
async function exportFile(reportId:string,kind:'pdf'|'xlsx'){
 const created=await api<{id:string}>(`reports/${reportId}/exports`,{format:kind});
 for(let tries=0;tries<150;tries++){
  await wait(2000);
  const result=await api<{state:string;url?:string;error?:string}>(`exports/${created.id}`);
  if(result.state==='ready'&&result.url){window.location.assign(result.url);return}
  if(result.state==='failed')throw new Error(result.error||'ساخت فایل کامل نشد.');
 }
 throw new Error('ساخت فایل طولانی شده است؛ چند دقیقه بعد دوباره خروجی بگیرید.');
}
function Stat({icon,tone,label,hint,value,suffix}:{icon:IconSvgElement;tone:string;label:string;hint:string;value:number|null;suffix?:string}){
 return <motion.article layout className="flex min-h-[148px] flex-col justify-between border border-border bg-card p-5 shadow-[0_8px_35px_-22px_rgba(21,51,102,0.3)]" style={{borderRadius:20,borderWidth:1}}>
  <div className="flex items-center gap-2.5"><span className={`flex size-10 items-center justify-center rounded-xl ${tone}`}><HugeiconsIcon icon={icon} size={20}/></span><span className="text-xs text-muted-foreground">{label}</span></div>
  <div className="mt-3 flex items-baseline gap-1.5">{value===null?<span className="text-3xl font-bold text-muted-foreground/50">—</span>:<NumberTicker key={value} value={value} decimalPlaces={Number.isInteger(value)?0:2} className="text-3xl font-bold"/>}{value!==null&&suffix?<em className="text-xs not-italic text-muted-foreground">{suffix}</em>:null}</div>
  <small className="text-[11px] text-muted-foreground">{hint}</small>
 </motion.article>;
}
/** صفحهٔ مدیریتی با بازخورد آشکار برای کارهای طولانی. */
function App(){
 const config=window.scConfig;
 const brand=config.brand;
 const toasts=useToasts();
 const pushToast=toasts.push;
 const [status,setStatus]=useState<Status|null>(null);
 const [calendar,setCalendar]=useState<Calendar>('jalali');
 const [preset,setPreset]=useState('30');
 const [dates,setDates]=useState<[string,string]>(()=>presetRange('30',safeToday(config.today),'jalali'));
 const [products,setProducts]=useState<Product[]>([]);
 const [categories,setCategories]=useState<Product[]>([]);
 const [currency,setCurrency]=useState(config.currency);
 const [report,setReport]=useState<Report|null>(null);
 const [reportId,setReportId]=useState('');
 const [page,setPage]=useState(1);
 const [search,setSearch]=useState('');
 const [query,setQuery]=useState('');
 const [sort,setSort]=useState('amount');
 const [direction,setDirection]=useState('desc');
 const fresh=useRef(false);
 const today=safeToday(status?.today||config.today);
 const notifyError=useCallback((e:unknown)=>pushToast('error','عملیات کامل نشد',(e as Error).message),[pushToast]);
 /** وضعیت صف در پس‌زمینه نوسازی می‌شود؛ در زمان آماده‌سازی تندتر. */
 const refreshStatus=useCallback(()=>api<Status>('status').then(setStatus),[]);
 useEffect(()=>{let active=true;let timer=0;const tick=()=>{refreshStatus().catch(e=>{if(active)notifyError(e)}).finally(()=>{if(active)timer=window.setTimeout(tick,status?.scanning||!status?.ready?5000:30000)})};tick();return()=>{active=false;clearTimeout(timer)}},[refreshStatus,notifyError,status?.scanning,status?.ready]);
 /** جست‌وجو پس از توقف تایپ ارسال می‌شود تا هر نویسه یک درخواست نسازد. */
 useEffect(()=>{const timer=setTimeout(()=>setQuery(search),300);return()=>clearTimeout(timer)},[search]);
 const tableQuery=`page=${page}&q=${encodeURIComponent(query)}&sort=${sort}&direction=${direction}`;
 /** صفحه، جست‌وجو و ترتیب سمت سرور خوانده می‌شوند؛ بلافاصله پس از ساخت، نتیجهٔ همان درخواست کافی است. */
 useEffect(()=>{if(!reportId)return;if(fresh.current){fresh.current=false;return}let active=true;api<Report>(`reports/${reportId}?${tableQuery}`).then(result=>{if(active)setReport(result)}).catch(e=>{if(active)notifyError(e)});return()=>{active=false}},[reportId,tableQuery,notifyError]);
 const setRange=(from:string,to:string,key='custom')=>{setPreset(key);setDates(from<=to?[from,to]:[to,from])};
 const changePreset=(key:string)=>{if(key==='custom'){setPreset(key);return}const [from,to]=presetRange(key,today,calendar);setRange(from,to,key)};
 const changeCalendar=(next:Calendar)=>{setCalendar(next);if(preset==='month'||preset==='year'){const [from,to]=presetRange(preset,today,next);setDates([from,to])}};
 /** ساخت گزارش با فیلتر صریح؛ نتیجهٔ قبلی هنگام تغییر فیلتر تصادفی جایگزین نمی‌شود. */
 const generate=async()=>{
  try{
   const filters:Filters={from:dates[0],to:dates[1],currency,products:products.map(p=>Number(p.id)),categories:categories.map(c=>Number(c.id)),calendar};
   const created=await api<{id:string}>('reports',filters);
   const ready=await waitForReport(created.id,'page=1&q=&sort=amount&direction=desc');
   fresh.current=true;setPage(1);setSearch('');setQuery('');setSort('amount');setDirection('desc');setReport(ready);setReportId(created.id);
  }catch(e){notifyError(e);throw e}
 };
 const changeSort=(key:string)=>{setPage(1);setSort(key);setDirection(key===sort&&direction==='desc'?'asc':'desc')};
 const download=(kind:'pdf'|'xlsx')=>async()=>{try{await exportFile(reportId,kind);toasts.push('success','فایل آماده شد','دانلود آغاز شد.')}catch(e){notifyError(e);throw e}};
 const rebuild=async()=>{try{await api('sync',{rebuild:true});toasts.push('success','بازسازی نمایه آغاز شد','سفارش‌ها در پس‌زمینه دوباره خوانده می‌شوند.');await refreshStatus()}catch(e){notifyError(e);throw e}};
 const summary=report?.state==='ready'?report.summary:null;
 // واحد پول از نسخهٔ ثابت گزارش خوانده می‌شود، نه از انتخاب فعلی فیلتر.
 const reportCurrency=report?.summary.unit||report?.filters.currency||currency;
 const currencies=useMemo(()=>Array.from(new Set([currency,...(status?.currencies||[])])).map(value=>({value,label:CURRENCY_LABELS[value]||value})),[currency,status?.currencies]);
 const syncing=!!status&&(!status.ready||!!status.error);
 const progress=status?.progress.total?Math.min(1,status.progress.processed/status.progress.total):undefined;
 return <MotionConfig reducedMotion="user"><main className="sc-app">
  {brand?.yarn&&<div className="sc-yarn" aria-hidden/>}
  <div className="mx-auto max-w-[1390px]">
   <header className="flex items-center justify-between gap-4 border-b border-border pb-6 pt-1.5">
    {brand?.custom&&brand.logo?<div className="flex items-center gap-3"><img src={brand.logo} alt={brand.name} className="h-14 w-auto"/><span className="hidden h-9 w-px bg-border sm:block"/><small className="hidden max-w-[16rem] text-xs leading-5 text-muted-foreground sm:block">{brand.tagline}</small></div>:<div className="flex items-center gap-2.5"><span className="flex size-11 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-[0_10px_24px_-10px_hsl(var(--primary))]"><HugeiconsIcon icon={Analytics01Icon} size={22}/></span><div><strong className="block text-base leading-tight" dir="ltr">Sales Calculator</strong><small className="text-[11px] text-muted-foreground">گزارش‌های فروش ووکامرس</small></div></div>}
    <span className="flex max-w-[45%] items-center gap-2 truncate rounded-full border border-border bg-card px-3.5 py-2 text-xs text-muted-foreground"><HugeiconsIcon icon={Store01Icon} size={16}/><span className="truncate">{config.store}</span><span className="size-1.5 shrink-0 rounded-full bg-success shadow-[0_0_0_4px_hsl(var(--success)/0.15)]"/></span>
   </header>
   <div className="py-8">
    <p className="flex items-center gap-1.5 text-xs font-semibold text-primary"><HugeiconsIcon icon={ChartIncreaseIcon} size={15}/> تحلیل فروش فروشگاه</p>
    <h1 className="mt-1.5 text-[28px] font-bold leading-snug sm:text-[32px]">گزارش فروش محصولات</h1>
    <p className="mt-1 text-sm text-muted-foreground">عملکرد هر محصول و متغیر را در بازهٔ دلخواه ببینید و خروجی دقیق بگیرید.</p>
   </div>
   {syncing&&<motion.div initial={{opacity:0,y:-6}} animate={{opacity:1,y:0}} className="mb-5 flex flex-wrap items-center gap-4 border border-warning/25 bg-warning/5 px-5 py-4" style={{borderRadius:20,borderWidth:1}} role="status">
    <StatusMark status={status?.error?'failed':status?.scanning?'running':'pending'} progress={progress} size={34} label={null} color="hsl(var(--warning))"/>
    <div className="min-w-[200px] flex-1">
     <strong className="block text-sm text-foreground">{status?.error?'پردازش نیاز به بررسی دارد':status?.scanning?'در حال آماده‌سازی داده‌ها':'در انتظار آماده‌سازی داده‌ها'}</strong>
     <p className="mt-0.5 text-xs text-muted-foreground">{status?.error||'سفارش‌های فروشگاه در پس‌زمینه بررسی می‌شوند. پس از اتمام، گزارش‌ها فعال خواهند شد.'}</p>
     {status?.scanning&&<div className="mt-2.5 h-1.5 max-w-sm overflow-hidden rounded-full bg-warning/15"><motion.span className="block h-full rounded-full bg-warning" animate={{width:`${Math.max(4,(progress||0)*100)}%`}}/></div>}
    </div>
    {status?.scanning&&<span className="sc-tabular text-xs text-muted-foreground">{format(status.progress.processed)} / {format(status.progress.total)}</span>}
    {(status?.error||!status?.scanning)&&<StatusButton onAction={rebuild} label="بازسازی نمایه" loadingLabel="در حال شروع…" successLabel="آغاز شد" icon={RefreshIcon} variant="outline"/>}
   </motion.div>}
   <section className="border border-border bg-card shadow-[0_8px_35px_-22px_rgba(21,51,102,0.3)]" style={{borderRadius:24,borderWidth:1}} aria-labelledby="sc-filters">
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
     <div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary"><HugeiconsIcon icon={FilterIcon} size={19}/></span><div><h2 id="sc-filters" className="text-base font-semibold">فیلترهای گزارش</h2><p className="text-xs text-muted-foreground">بازه و محصولات موردنظر را انتخاب کنید</p></div></div>
     <DiscreteTabs tabs={CALENDARS} value={calendar} onChange={changeCalendar} label="نوع تقویم"/>
    </div>
    <div className="grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
     <SoftSelect label="بازهٔ سریع" value={preset} onValueChange={changePreset} options={PRESETS}/>
     <div><p className="mb-2 text-sm text-muted-foreground">از تاریخ</p><DateField calendar={calendar} label="شروع بازه" today={parseDay(today)} value={parseDay(dates[0])} onChange={date=>date&&setRange(isoDay(date),dates[1])}/></div>
     <div><p className="mb-2 text-sm text-muted-foreground">تا تاریخ</p><DateField calendar={calendar} label="پایان بازه" today={parseDay(today)} value={parseDay(dates[1])} onChange={date=>date&&setRange(dates[0],isoDay(date))}/></div>
     <div><p className="mb-2 text-sm text-muted-foreground">دسته‌بندی</p><ProductPicker source="categories" selected={categories} onChange={setCategories}/></div>
     <div><p className="mb-2 text-sm text-muted-foreground">محصولات</p><ProductPicker selected={products} onChange={setProducts}/></div>
     <SoftSelect label="واحد پول" value={currency} onValueChange={setCurrency} options={currencies}/>
    </div>
    <div className="flex flex-col-reverse items-stretch justify-between gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center">
     <span className="text-center text-[11px] text-muted-foreground sm:text-start">مبنای گزارش: سفارش‌های پرداخت‌شده، پس از تخفیف و مرجوعی؛ بدون مالیات و هزینهٔ ارسال</span>
     <StatusButton onAction={generate} disabled={!status?.ready||!!status?.error} label="نمایش گزارش" loadingLabel="در حال ساخت گزارش…" successLabel="گزارش آماده شد" icon={Analytics01Icon}/>
    </div>
   </section>
   {status?.ready&&status.scanning&&<p className="mt-3 rounded-xl border border-primary/15 bg-accent px-4 py-2.5 text-xs text-accent-foreground">همگام‌سازی دوره‌ای در جریان است. نسخهٔ هر گزارش هنگام ساخت ثابت می‌ماند.</p>}
   <div className="my-6 grid gap-4 md:grid-cols-3">
    <Stat icon={ShoppingBasket01Icon} tone="bg-accent text-primary" label="تعداد خالص فروخته‌شده" hint="عدد محصول پس از مرجوعی" value={summary?Number(summary.quantity):null}/>
    <Stat icon={Coins01Icon} tone="bg-success/10 text-success" label="مبلغ خالص محصولات" hint="بدون مالیات و هزینهٔ ارسال" value={summary?Number(summary.amount):null} suffix={reportCurrency}/>
    <Stat icon={Invoice03Icon} tone="bg-violet-500/10 text-violet-600" label="سفارش‌های مرتبط" hint="در بازهٔ انتخاب‌شده" value={summary?Number(summary.orders):null}/>
   </div>
   {report?.state==='ready'?<motion.section initial={{opacity:0,y:12}} animate={{opacity:1,y:0}} aria-labelledby="sc-details">
    <div className="mb-4 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
     <div><p className="text-xs font-semibold text-primary">نتیجهٔ گزارش</p><h2 id="sc-details" className="mt-1 text-xl font-bold">جزئیات فروش</h2><p className="mt-0.5 text-[11px] text-muted-foreground">نسخهٔ داده‌ها: {summary?.asOf?new Date(summary.asOf).toLocaleString('fa-IR'):'—'} · منطقهٔ زمانی: {summary?.timezone}</p></div>
     <div className="grid grid-cols-2 gap-2 sm:flex">
      <StatusButton onAction={download('xlsx')} label="خروجی اکسل" loadingLabel="در حال ساخت…" successLabel="دانلود شد" icon={Xls02Icon} variant="outline"/>
      <StatusButton onAction={download('pdf')} label="دریافت PDF" loadingLabel="در حال ساخت…" successLabel="دانلود شد" icon={Pdf02Icon}/>
     </div>
    </div>
    <ReportTable report={report} sort={sort} direction={direction} search={search} onSearch={value=>{setSearch(value);setPage(1)}} onSort={changeSort} onPage={setPage}/>
    {Number(summary?.unallocated)!==0&&<p className="mt-3 rounded-xl border border-primary/15 bg-accent px-4 py-2.5 text-xs text-accent-foreground">مرجوعی تخصیص‌نیافته: {format(Math.abs(Number(summary?.unallocated)))} {reportCurrency}. این مبلغ در جمع فروش محصولات توزیع نشده است.</p>}
   </motion.section>:<div className="border border-dashed border-border bg-card/60 px-4 py-10 text-center" style={{borderRadius:20,borderWidth:1}}>
    <span className="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-accent text-primary"><HugeiconsIcon icon={Analytics01Icon} size={26}/></span>
    <h2 className="text-base font-semibold">گزارش فروش آمادهٔ نمایش است</h2>
    <p className="mt-1 text-xs text-muted-foreground">بازهٔ زمانی و محصولات را انتخاب کنید و «نمایش گزارش» را بزنید.</p>
   </div>}
   <footer className="flex items-center justify-center gap-2 py-7 text-center text-[11px] text-muted-foreground/70">{brand?.custom&&brand.mark?<><img src={brand.mark} alt="" className="size-5"/>{brand.name} · گزارش فروش اختصاصی</>:'Sales Calculator · گزارش دقیق برای تصمیم‌گیری بهتر'}</footer>
  </div>
  <ToastViewport items={toasts.items} onDismiss={toasts.dismiss}/>
 </main></MotionConfig>
}
const root=document.getElementById('sales-calculator');if(root)createRoot(root).render(<App/>);
