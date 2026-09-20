document.querySelectorAll('[data-dismiss]').forEach((button)=>button.addEventListener('click',()=>button.closest('.alert')?.remove()));
const menu=document.querySelector('[data-menu]');const backdrop=document.querySelector('[data-backdrop]');
function toggleMenu(force){const open=force??!document.body.classList.contains('menu-open');document.body.classList.toggle('menu-open',open);menu?.setAttribute('aria-expanded',String(open));}
menu?.addEventListener('click',()=>toggleMenu());backdrop?.addEventListener('click',()=>toggleMenu(false));
document.addEventListener('keydown',(event)=>{if(event.key==='Escape')toggleMenu(false);});
document.querySelectorAll('[data-confirm]').forEach((form)=>form.addEventListener('submit',(event)=>{if(!window.confirm(form.dataset.confirm||'Are you sure?'))event.preventDefault();}));
