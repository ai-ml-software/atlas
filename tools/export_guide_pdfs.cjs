const fs=require('node:fs'),path=require('node:path');
const {pathToFileURL}=require('node:url');
const {chromium}=require('../e2e/node_modules/playwright');
const OUT=path.resolve(__dirname,'../docs/guides');
(async()=>{const browser=await chromium.launch({headless:true,channel:'msedge'});try{const page=await browser.newPage();for(const name of ['ADMIN_USER_GUIDE','STUDENT_USER_GUIDE','INSTRUCTOR_USER_GUIDE','MOBILE_INSTALLATION']){await page.goto(pathToFileURL(path.join(OUT,name+'.html')).href);await page.pdf({path:path.join(OUT,name+'.pdf'),format:'A4',printBackground:true,margin:{top:'18mm',bottom:'18mm',left:'16mm',right:'16mm'},displayHeaderFooter:true,headerTemplate:'<span></span>',footerTemplate:'<div style="font:9px Arial;width:100%;text-align:center;color:#666">ALTUS guide · <span class="pageNumber"></span> / <span class="totalPages"></span></div>'});console.log(name+'.pdf');}}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
