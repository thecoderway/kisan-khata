const DATE_INPUT_SELECTOR = 'input[type="date"]:not([data-kk-datepicker-ready])';
const MONTHS = ['जनवरी','फ़रवरी','मार्च','अप्रैल','मई','जून','जुलाई','अगस्त','सितंबर','अक्टूबर','नवंबर','दिसंबर'];
const WEEKDAYS = ['रवि','सोम','मंगल','बुध','गुरु','शुक्र','शनि'];
const parseDate = (value) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
    const [y,m,d] = value.split('-').map(Number);
    const date = new Date(y,m-1,d);
    return date.getFullYear()===y && date.getMonth()===m-1 && date.getDate()===d ? date : null;
};
const isoDate = (d) => [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
const displayDate = (d) => d ? [String(d.getDate()).padStart(2,'0'),String(d.getMonth()+1).padStart(2,'0'),d.getFullYear()].join('/') : '';
const sameDay = (a,b) => Boolean(a && b && isoDate(a)===isoDate(b));

const enhanceDateInput = (input) => {
    if (!(input instanceof HTMLInputElement) || input.dataset.kkDatepickerReady==='true' || !input.parentNode) return;
    const originalId=input.id, originalRequired=input.required;
    const wrapper=document.createElement('div');
    wrapper.className='kk-datepicker'; wrapper.dataset.kkDatepicker='true';
    input.parentNode.insertBefore(wrapper,input); wrapper.appendChild(input);
    const display=document.createElement('input');
    display.type='text'; display.id=originalId ? originalId+'-display' : '';
    display.className=input.className; display.classList.add('kk-datepicker-input');
    display.placeholder='DD/MM/YYYY'; display.inputMode='numeric'; display.autocomplete='off';
    display.setAttribute('aria-haspopup','dialog'); display.setAttribute('aria-expanded','false');
    display.setAttribute('aria-label',input.getAttribute('aria-label') || input.labels?.[0]?.textContent?.trim() || 'तारीख चुनें');
    input.type='hidden'; input.required=false; input.removeAttribute('class');
    input.dataset.kkDatepickerReady='true'; input.setAttribute('aria-hidden','true'); input.tabIndex=-1;
    const trigger=document.createElement('button');
    trigger.type='button'; trigger.className='kk-datepicker-trigger';
    trigger.setAttribute('aria-label','कैलेंडर खोलें');
    trigger.innerHTML='<i class="ti ti-calendar" aria-hidden="true"></i>';
    const pop=document.createElement('div'); pop.className='kk-calendar-popover';
    pop.hidden=true; pop.setAttribute('role','dialog'); pop.setAttribute('aria-label','तारीख चुनें');
    wrapper.append(display,trigger,pop);
    if(originalId) document.querySelectorAll('label[for="'+CSS.escape(originalId)+'"]').forEach(label=>label.htmlFor=display.id);
    display.required=originalRequired;
    let view=parseDate(input.value)||new Date(); view=new Date(view.getFullYear(),view.getMonth(),1);
    let opened=false;
    const sync=()=>{display.value=displayDate(parseDate(input.value));display.setCustomValidity(originalRequired&&!input.value?'कृपया तारीख चुनें।':'');trigger.disabled=input.disabled||input.readOnly;display.disabled=input.disabled;display.setAttribute('aria-expanded',String(opened));};
    const close=()=>{opened=false;pop.hidden=true;sync();};
    const choose=(date)=>{const val=isoDate(date);if((input.min&&val<input.min)||(input.max&&val>input.max))return;input.value=val;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));sync();close();};
    const render=()=>{
        const y=view.getFullYear(),m=view.getMonth(),first=new Date(y,m,1).getDay(),count=new Date(y,m+1,0).getDate();
        const selected=parseDate(input.value),today=new Date();pop.innerHTML='';
        const head=document.createElement('div');head.className='kk-calendar-header';
        const prev=document.createElement('button');prev.type='button';prev.className='kk-calendar-nav';prev.setAttribute('aria-label','पिछला महीना');prev.innerHTML='<i class="ti ti-chevron-left" aria-hidden="true"></i>';prev.addEventListener('click',()=>{view=new Date(y,m-1,1);render();});
        const title=document.createElement('strong');title.textContent=MONTHS[m]+' '+y;
        const next=document.createElement('button');next.type='button';next.className='kk-calendar-nav';next.setAttribute('aria-label','अगला महीना');next.innerHTML='<i class="ti ti-chevron-right" aria-hidden="true"></i>';next.addEventListener('click',()=>{view=new Date(y,m+1,1);render();});
        head.append(prev,title,next);
        const grid=document.createElement('div');grid.className='kk-calendar-grid';
        WEEKDAYS.forEach(day=>{const el=document.createElement('span');el.className='kk-calendar-weekday';el.textContent=day;grid.append(el);});
        for(let i=0;i<first;i++){const blank=document.createElement('span');blank.className='kk-calendar-blank';grid.append(blank);}
        for(let day=1;day<=count;day++){const date=new Date(y,m,day),val=isoDate(date),btn=document.createElement('button');btn.type='button';btn.className='kk-calendar-day';btn.textContent=String(day);btn.setAttribute('aria-label',displayDate(date));if(sameDay(date,selected)){btn.classList.add('is-selected');btn.setAttribute('aria-pressed','true');}if(sameDay(date,today))btn.classList.add('is-today');if((input.min&&val<input.min)||(input.max&&val>input.max))btn.disabled=true;btn.addEventListener('click',()=>choose(date));grid.append(btn);}
        const footer=document.createElement('div');footer.className='kk-calendar-footer';
        const clear=document.createElement('button');clear.type='button';clear.className='kk-calendar-action';clear.textContent='साफ़ करें';clear.disabled=originalRequired;clear.addEventListener('click',()=>{input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));sync();close();});
        const todayBtn=document.createElement('button');todayBtn.type='button';todayBtn.className='kk-calendar-action kk-calendar-today';todayBtn.textContent='आज';todayBtn.addEventListener('click',()=>choose(today));
        footer.append(clear,todayBtn);pop.append(head,grid,footer);
    };
    const open=()=>{if(input.disabled||input.readOnly)return;view=parseDate(input.value)||new Date();view=new Date(view.getFullYear(),view.getMonth(),1);render();opened=true;pop.hidden=false;sync();};
    display.addEventListener('click',open);trigger.addEventListener('click',open);});
    display.addEventListener('keydown',e=>{if(e.key==='Escape')close();if(e.key==='Enter'||e.key===' '){e.preventDefault();open();}});
    document.addEventListener('click',e=>{if(opened&&!wrapper.contains(e.target))close();});
    document.addEventListener('keydown',e=>{if(opened&&e.key==='Escape'){close();display.focus();}});
    input.form?.addEventListener('reset',()=>requestAnimationFrame(sync));input.addEventListener('change',sync);sync();
};
const scan=(root)=>{if(root instanceof HTMLInputElement&&root.matches('input[type="date"]'))enhanceDateInput(root);root.querySelectorAll?.(DATE_INPUT_SELECTOR).forEach(enhanceDateInput);};
export const initialiseDatepickers=(root=document)=>{
    scan(root);
    if(root!==document||!document.documentElement||document.documentElement.dataset.kkDatepickerObserver==='true')return;
    document.documentElement.dataset.kkDatepickerObserver='true';
    const observer=new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node instanceof HTMLElement)scan(node);})));
    observer.observe(document.documentElement,{childList:true,subtree:true});
};
