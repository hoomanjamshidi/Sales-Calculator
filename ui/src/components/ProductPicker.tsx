/** جست‌وجوی محصول روی نمایه و نگه‌داری انتخاب‌های چندگانه، با پنل فنری هم‌زبان با سلکت طرحک. */
import {useEffect,useId,useRef,useState} from 'react';
import {AnimatePresence,motion,useReducedMotion} from 'motion/react';
import {HugeiconsIcon} from '@hugeicons/react';
import {Cancel01Icon,PlusSignIcon,Search01Icon,Tick02Icon} from '@hugeicons/core-free-icons';
import {cn} from '@/lib/utils';
import {api} from '../api';
type Product={id:string;name:string;count?:number};
/** همین انتخاب‌گر برای دسته‌بندی‌ها هم به کار می‌رود؛ فهرست دسته یک‌بار گرفته و در مرورگر جست‌وجو می‌شود. */
type Props={selected:Product[];onChange:(items:Product[])=>void;source?:'products'|'categories'};
const TEXT={products:{noun:'محصول',all:'همهٔ محصولات',list:'فهرست محصولات',search:'جست‌وجوی محصول',empty:'محصولی پیدا نشد.'},categories:{noun:'دسته',all:'همهٔ دسته‌بندی‌ها',list:'فهرست دسته‌بندی‌ها',search:'جست‌وجوی دسته‌بندی',empty:'دسته‌بندی پیدا نشد.'}};
const panelSpring={type:'spring' as const,stiffness:420,damping:32,mass:0.75};
export function ProductPicker({selected,onChange,source='products'}:Props){
 const t=TEXT[source];
 const reduce=useReducedMotion()??false;
 const [query,setQuery]=useState('');const [results,setResults]=useState<Product[]>([]);const [open,setOpen]=useState(false);const [loading,setLoading]=useState(false);const [failed,setFailed]=useState(false);
 const root=useRef<HTMLDivElement>(null);const listId=useId();
 /** کلیک بیرون یا Escape فهرست را می‌بندد. */
 useEffect(()=>{if(!open)return;const outside=(event:PointerEvent)=>{if(root.current&&!root.current.contains(event.target as Node))setOpen(false)};const key=(event:KeyboardEvent)=>{if(event.key==='Escape')setOpen(false)};document.addEventListener('pointerdown',outside);document.addEventListener('keydown',key);return()=>{document.removeEventListener('pointerdown',outside);document.removeEventListener('keydown',key)}},[open]);
 useEffect(()=>{if(!open)return;const controller=new AbortController();setLoading(true);setFailed(false);const timer=setTimeout(()=>{(source==='categories'?api<Product[]>('categories',undefined,controller.signal).then(list=>list.filter(item=>item.name.includes(query.trim()))):api<Product[]>('products?q='+encodeURIComponent(query),undefined,controller.signal)).then(setResults).catch(()=>{if(!controller.signal.aborted)setFailed(true)}).finally(()=>{if(!controller.signal.aborted)setLoading(false)})},250);return()=>{clearTimeout(timer);controller.abort()}},[query,open,source]);
 const isSelected=(item:Product)=>selected.some(s=>s.id===item.id);
 const toggle=(item:Product)=>{onChange(isSelected(item)?selected.filter(s=>s.id!==item.id):[...selected,item]);setQuery('')};
 return <div className="relative w-full" ref={root}>
  <div className={cn('flex h-14 items-center gap-2.5 border border-border bg-background px-3.5 transition-shadow focus-within:ring-2 focus-within:ring-ring',open&&'border-foreground/20')} style={{borderRadius:20,borderWidth:1}}>
   <span className="text-muted-foreground"><HugeiconsIcon icon={Search01Icon} size={20}/></span>
   <div className="flex min-w-0 flex-1 flex-col leading-tight">
    <input type="text" role="combobox" aria-expanded={open} aria-controls={listId} aria-autocomplete="list" value={query} onFocus={()=>setOpen(true)} onChange={e=>{setQuery(e.target.value);setOpen(true)}} placeholder={selected.length?`${new Intl.NumberFormat('fa-IR').format(selected.length)} ${t.noun} انتخاب شده`:t.all} aria-label={t.search} className="w-full min-w-0 bg-transparent text-[15px] font-medium text-foreground outline-none placeholder:text-foreground"/>
   </div>
   {selected.length>0&&<button type="button" className="flex size-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-foreground" aria-label="پاک کردن انتخاب‌ها" onClick={()=>onChange([])}><HugeiconsIcon icon={Cancel01Icon} size={16}/></button>}
  </div>
  <AnimatePresence>
   {open&&<motion.div id={listId} role="listbox" aria-multiselectable="true" aria-label={t.list} initial={reduce?{opacity:1}:{opacity:0,y:-8,scale:0.97,filter:'blur(4px)'}} animate={{opacity:1,y:0,scale:1,filter:'blur(0px)'}} exit={reduce?{opacity:0}:{opacity:0,y:-6,scale:0.98,filter:'blur(3px)'}} transition={panelSpring} className="absolute inset-x-0 top-[calc(100%+8px)] z-40 max-h-72 overflow-auto border border-border bg-card p-1.5 shadow-[0_12px_40px_-18px_rgba(0,0,0,0.45)]" style={{borderRadius:18,borderWidth:1}}>
    <button type="button" onClick={()=>{onChange([]);setOpen(false);setQuery('')}} className="flex w-full items-center justify-between rounded-2xl px-3 py-2.5 text-start text-sm font-medium text-primary hover:bg-accent">{t.all}{selected.length===0&&<HugeiconsIcon icon={Tick02Icon} size={16}/>}</button>
    {results.map((item,index)=>{const active=isSelected(item);return <motion.button key={item.id} type="button" role="option" aria-selected={active} initial={reduce?false:{opacity:0,y:10}} animate={{opacity:1,y:0}} transition={{...panelSpring,delay:reduce?0:Math.min(index,12)*0.02}} onClick={()=>toggle(item)} className="flex w-full items-center justify-between gap-3 rounded-2xl px-3 py-2.5 text-start text-sm text-foreground hover:bg-accent">
     <span className="truncate">{item.name}{item.count!==undefined&&<span className="ms-1.5 text-xs text-muted-foreground">({new Intl.NumberFormat('fa-IR').format(item.count)})</span>}</span>
     <span className={cn('flex size-6 shrink-0 items-center justify-center rounded-full border',active?'border-success bg-success text-white':'border-border text-muted-foreground')}><HugeiconsIcon icon={active?Tick02Icon:PlusSignIcon} size={13}/></span>
    </motion.button>})}
    {loading&&results.length===0&&<p className="px-3 py-3 text-xs text-muted-foreground">در حال جست‌وجو…</p>}
    {!loading&&failed&&<p className="px-3 py-3 text-xs text-destructive">جست‌وجوی محصول انجام نشد؛ دوباره تلاش کنید.</p>}
    {!loading&&!failed&&results.length===0&&<p className="px-3 py-3 text-xs text-muted-foreground">{t.empty}</p>}
   </motion.div>}
  </AnimatePresence>
  {selected.length>0&&<div className="mt-2 flex flex-wrap gap-1.5">
   <AnimatePresence initial={false}>
    {selected.map(item=><motion.button layout key={item.id} type="button" initial={reduce?false:{opacity:0,scale:0.8}} animate={{opacity:1,scale:1}} exit={{opacity:0,scale:0.8}} onClick={()=>onChange(selected.filter(s=>s.id!==item.id))} aria-label={`حذف ${item.name}`} className="inline-flex max-w-[14rem] items-center gap-1.5 rounded-full border border-primary/20 bg-accent px-2.5 py-1 text-xs text-accent-foreground hover:border-primary/40"><span className="truncate">{item.name}</span><HugeiconsIcon icon={Cancel01Icon} size={12}/></motion.button>)}
   </AnimatePresence>
  </div>}
 </div>
}
