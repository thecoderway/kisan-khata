import { attachVillageDropdown } from './village-dropdown.js';
const norm = (v) => String(v ?? '').normalize('NFC').trim().toLocaleLowerCase();
export const filterCustomerOptions = (options, query) => {
 const q=norm(query);
 return Array.from(options||[]).filter(o=>o.value && (!q || norm(o.textContent).includes(q) || norm(o.dataset?.search||'').includes(q) || norm(o.value).includes(q)));
};
export const findCustomerOption = (options,label) => { const q=norm(label); return q ? Array.from(options||[]).find(o=>o.value && norm(o.textContent)===q)||null : null; };
export const syncCustomerFilterLabel = (input,select) => { if(!input||!select)return; const selected=Array.from(select.options||[]).find(o=>o.value&&o.value===select.value); input.value=selected?selected.textContent.trim():''; };
export const initialiseCustomerFilter = (input,select) => {
 if(!input||!select||input.dataset.customerFilterReady==='true')return;
 input.dataset.customerFilterReady='true'; input.setAttribute('role','combobox'); input.setAttribute('aria-autocomplete','list'); input.setAttribute('aria-haspopup','listbox'); input.setAttribute('aria-expanded','false');
 const menu=input.ownerDocument.createElement('div'); menu.id=input.id+'-options'; menu.className='list-group shadow'; menu.setAttribute('role','listbox'); menu.hidden=true;
 const setOpen=attachVillageDropdown(input,menu); input.setAttribute('aria-controls',menu.id);
 let active=-1, updating=false;
 const close=()=>{setOpen(false);active=-1;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');};
 const choose=o=>{if(!o)return;select.value=o.value;updating=true;select.dispatchEvent(new Event('change',{bubbles:true}));updating=false;input.value=o.textContent.trim();close();};
 const render=()=>{const matches=filterCustomerOptions(select.options,input.value);menu.replaceChildren();active=-1;
 if(!matches.length){const empty=input.ownerDocument.createElement('div');empty.className='list-group-item text-muted small';empty.textContent='कोई ग्राहक नहीं मिला।';menu.append(empty);}
 else matches.forEach((o,i)=>{const b=input.ownerDocument.createElement('button');b.type='button';b.className='list-group-item list-group-item-action';b.id=menu.id+'-'+i;b.dataset.optionIndex=String(Array.from(select.options).indexOf(o));b.setAttribute('role','option');b.setAttribute('aria-selected','false');b.textContent=o.textContent.trim();b.addEventListener('mousedown',e=>e.preventDefault());b.addEventListener('click',()=>choose(o));menu.append(b);});
 setOpen(true);input.setAttribute('aria-expanded','true');};
 input.addEventListener('input',()=>{const selected=Array.from(select.options||[]).find(o=>o.value===select.value);if(selected&&norm(input.value)!==norm(selected.textContent)){select.value='';updating=true;select.dispatchEvent(new Event('change',{bubbles:true}));updating=false;}render();});
 input.addEventListener('focus',render);
 input.addEventListener('keydown',e=>{if(e.key==='Escape'){close();return;}if(menu.hidden)return;const opts=Array.from(menu.querySelectorAll('[role="option"]'));if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(!opts.length)return;active=Math.max(0,Math.min(opts.length-1,active+(e.key==='ArrowDown'?1:-1)));opts.forEach((o,i)=>o.setAttribute('aria-selected',i===active?'true':'false'));input.setAttribute('aria-activedescendant',opts[active].id);opts[active].scrollIntoView?.({block:'nearest'});}if(e.key==='Enter'){const exact=findCustomerOption(select.options,input.value);const option=active>=0?select.options[opts[active]?.dataset.optionIndex]:exact;if(option){e.preventDefault();choose(option);}}});
 select.addEventListener('change',()=>{if(!updating){syncCustomerFilterLabel(input,select);close();}});
 input.ownerDocument.addEventListener('click',e=>{if(!input.contains(e.target)&&!menu.contains(e.target))close();});
 syncCustomerFilterLabel(input,select);
};
