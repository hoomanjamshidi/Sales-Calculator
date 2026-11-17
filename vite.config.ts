/** ساخت دارایی‌های محلی با نام ثابت برای بارگذاری در وردپرس؛ هدف ES2022 مانع تعریف متغیر سراسری کمکی (مانند $) بیرون از IIFE می‌شود. */
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({plugins:[react()],resolve:{alias:{'@':fileURLToPath(new URL('./ui/src',import.meta.url))}},define:{'process.env.NODE_ENV':JSON.stringify('production')},build:{target:'es2022',outDir:'assets/build',emptyOutDir:true,lib:{entry:'ui/src/main.tsx',name:'SalesCalculator',formats:['iife'],fileName:()=> 'app.js'},rollupOptions:{output:{assetFileNames:'app.[ext]'}}},test:{include:['ui/src/**/*.test.ts'],alias:{'@':fileURLToPath(new URL('./ui/src',import.meta.url))}}});
