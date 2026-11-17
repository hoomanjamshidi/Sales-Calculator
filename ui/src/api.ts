/** ارتباط هم‌مبدأ با کنترل nonce، لغو درخواست و پیام خطای فارسی. */
export async function api<T>(path:string,body?:unknown,signal?:AbortSignal):Promise<T> {
 // وردپرس با پیوند یکتای ساده، مسیر REST را در پارامتر rest_route می‌گذارد.
 const base=new URL(window.scConfig.api);
 const [pathname,query='']=path.split('?');
 let url:URL;
 if(base.searchParams.has('rest_route')){
  base.searchParams.set('rest_route',(base.searchParams.get('rest_route')||'').replace(/\/$/,'')+'/'+pathname);
  for(const [key,value] of new URLSearchParams(query))base.searchParams.set(key,value);
  url=base;
 }else{url=new URL(path,window.scConfig.api)}
 const response=await fetch(url,{method:body===undefined?'GET':'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':window.scConfig.nonce},body:body===undefined?undefined:JSON.stringify(body),signal});
 // خطای PHP یا صفحهٔ ورود ممکن است HTML برگرداند؛ پیام فارسی جای خطای تجزیهٔ JSON می‌نشیند.
 const data=await response.json().catch(()=>null);
 if(!response.ok||data===null) throw new Error(data?.message || 'ارتباط با سرور کامل نشد.');
 return data as T;
}
