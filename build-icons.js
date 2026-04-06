
const fs=require('fs');

const FA='./fontawesome/svgs';
const OUT='./output';
const ICONS=require('./icons-list.json');

fs.mkdirSync(OUT+'/svg',{recursive:true});

let symbols=[];

function find(icon){
 for(let s of ['solid','regular','brands']){
  let p=`${FA}/${s}/${icon}.svg`;
  if(fs.existsSync(p)) return p;
 }
 return null;
}

ICONS.forEach(icon=>{
 let p=find(icon);
 if(!p){ console.log('Missing:',icon); return; }

 let svg=fs.readFileSync(p,'utf8');
 fs.writeFileSync(`${OUT}/svg/${icon}.svg`,svg);

 let vb=svg.match(/viewBox="([^"]+)"/)[1];
 let inner=svg.replace(/^[\s\S]*?<svg[^>]*>/,'').replace('</svg>','');

 symbols.push(`<symbol id="${icon}" viewBox="${vb}">${inner}</symbol>`);
});

fs.writeFileSync(`${OUT}/icons.svg`,
`<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
${symbols.join('\n')}
</svg>`);

console.log('DONE');
