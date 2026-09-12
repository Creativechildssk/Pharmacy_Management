document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('table.datatable').forEach(table=>{
    if(window.DataTable){new DataTable(table,{pageLength:25,order:[]});}
  });
  document.querySelectorAll('[data-confirm]').forEach(el=>{
    el.addEventListener('click',async e=>{
      if(!window.Swal)return;
      e.preventDefault();
      const result=await Swal.fire({title:el.dataset.confirm||'Confirm action?',icon:'warning',showCancelButton:true,confirmButtonText:'Continue'});
      if(result.isConfirmed){ if(el.tagName==='A') location.href=el.href; else el.closest('form')?.submit(); }
    });
  });
  document.querySelectorAll('[data-flash]').forEach(el=>{
    if(window.Swal){Swal.fire({text:el.dataset.flash,icon:el.dataset.flashType||'success',timer:2400,showConfirmButton:false});}
  });
});
