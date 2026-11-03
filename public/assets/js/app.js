'use strict';
document.querySelector('.menu-toggle')?.addEventListener('click',function(){const open=document.body.classList.toggle('nav-open');this.setAttribute('aria-expanded',String(open));});
const confirmation=document.getElementById('confirm-dialog');let pendingForm=null;
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(form.dataset.confirmed==='yes')return;event.preventDefault();pendingForm=form;document.getElementById('confirm-message').textContent=form.dataset.confirm;confirmation.showModal();}));
document.getElementById('confirm-cancel')?.addEventListener('click',()=>{confirmation.close();pendingForm=null;});
document.getElementById('confirm-accept')?.addEventListener('click',()=>{if(pendingForm){pendingForm.dataset.confirmed='yes';confirmation.close();pendingForm.requestSubmit();}});
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
document.querySelectorAll('input[type=file]').forEach(input=>input.addEventListener('change',()=>{input.setCustomValidity(input.files[0]?.size>5*1024*1024?'Maximum file size is 5 MB.':'');input.reportValidity();}));
// Prevent accidental repeated submission; server-side transactions enforce approval integrity.
document.querySelectorAll('form[method=post]').forEach(form=>form.addEventListener('submit',()=>{if(form.dataset.confirm && form.dataset.confirmed!=='yes')return;setTimeout(()=>form.querySelectorAll('button[type=submit],button:not([type])').forEach(button=>button.disabled=true),0);}));
