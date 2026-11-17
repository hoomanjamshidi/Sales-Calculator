/**
 * کلاس‌ها در محدودهٔ ریشهٔ افزونه معتبر هستند و reset سراسری نداریم.
 * توکن‌های رنگی FarsiUI/طرحک (card، border، accent و …) به متغیرهای CSS داخل #sales-calculator وصل‌اند
 * تا کامپوننت‌های طرحک بدون تغییر ظاهر پیشخوان وردپرس کار کنند.
 */
const token=name=>`hsl(var(--${name}) / <alpha-value>)`;
export default {
  content:['./ui/src/**/*.{ts,tsx}'],
  important:'#sales-calculator',
  corePlugins:{preflight:false},
  theme:{extend:{
    colors:{
      border:token('border'),input:token('input'),ring:token('ring'),background:token('background'),foreground:token('foreground'),
      primary:{DEFAULT:token('primary'),foreground:token('primary-foreground')},
      secondary:{DEFAULT:token('secondary'),foreground:token('secondary-foreground')},
      muted:{DEFAULT:token('muted'),foreground:token('muted-foreground')},
      accent:{DEFAULT:token('accent'),foreground:token('accent-foreground')},
      destructive:{DEFAULT:token('destructive'),foreground:token('destructive-foreground')},
      card:{DEFAULT:token('card'),foreground:token('card-foreground')},
      success:token('success'),warning:token('warning'),
    },
    fontFamily:{sans:['Vazir','Tahoma','sans-serif']},
    // کلاس‌های Tailwind 4 که در کامپوننت‌های طرحک به کار رفته‌اند.
    zIndex:{1:'1'},ringWidth:{3:'3px'},minHeight:{55:'13.75rem'},
  }},
  plugins:[],
};
