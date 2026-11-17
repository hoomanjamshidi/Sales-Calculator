/** ترکیب کلاس‌های Tailwind بدون تداخل؛ همان ابزاری که FarsiUI برای کامپوننت‌های طرحک فراهم می‌کند. */
import {clsx,type ClassValue} from 'clsx';
import {twMerge} from 'tailwind-merge';
export function cn(...inputs:ClassValue[]){return twMerge(clsx(inputs));}
