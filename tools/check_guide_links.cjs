const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../docs/guides'),missing=[];
for(const file of fs.readdirSync(root).filter(file=>file.endsWith('.html'))){
  for(const match of fs.readFileSync(path.join(root,file),'utf8').matchAll(/(?:href|src)="([^"]+)"/g)){
    const url=match[1].split('#')[0];
    if(url&&!/^(https?:|mailto:|tel:|data:|\/)/.test(url)&&!fs.existsSync(path.resolve(root,decodeURIComponent(url))))missing.push({file,url});
  }
}
console.log(JSON.stringify(missing,null,2));
process.exitCode=missing.length?1:0;
