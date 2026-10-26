import fs from 'node:fs';import path from 'node:path';
const root=path.resolve(process.argv[2]||'public');const origin='https://site.test';const base=process.argv[3]||'/';
function files(dir){return fs.readdirSync(dir,{withFileTypes:true}).flatMap(e=>e.isDirectory()?files(path.join(dir,e.name)):[path.join(dir,e.name)]);}
const pages=files(root).filter(p=>p.endsWith('.html'));const failures=[];let links=0;
for(const file of pages){const html=fs.readFileSync(file,'utf8');const route=base+path.relative(root,file).replaceAll('\\','/');
 for(const m of html.matchAll(/<a\b[^>]*\bhref\s*=\s*["']([^"']*)["'][^>]*>/gi)){
 links++;const href=m[1].replaceAll('&amp;','&');const source=path.relative(root,file);if(!href||href==='#'){failures.push({source,href,error:'Empty link'});continue;}
 let url;try{url=new URL(href,origin+route);}catch{failures.push({source,href,error:'Invalid URL'});continue;}if(url.origin!==origin)continue;
 if(!url.pathname.startsWith(base)){failures.push({source,href,error:'Outside deployment base'});continue;}
 let target=path.join(root,decodeURIComponent(url.pathname.slice(base.length)));if(fs.existsSync(target)&&fs.statSync(target).isDirectory())target=path.join(target,'index.html');
 if(!fs.existsSync(target)){failures.push({source,href,error:'Missing target'});continue;}
 if(url.hash&&target.endsWith('.html')){const id=decodeURIComponent(url.hash.slice(1));const ids=[...fs.readFileSync(target,'utf8').matchAll(/\bid\s*=\s*["']([^"']*)["']/g)].map(m=>m[1]);if(!ids.includes(id))failures.push({source,href,error:'Missing anchor'});}
 }
}
console.log(JSON.stringify({pages:pages.length,links,failures},null,2));if(failures.length)process.exitCode=1;
