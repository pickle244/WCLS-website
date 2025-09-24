/* =========================================================
   Password eye toggle on auth forms
   ========================================================= */
document.querySelectorAll('.toggle-password').forEach(icon => {
  icon.addEventListener('click', function () {
    const input = this.previousElementSibling;
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      this.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
      input.type = 'password';
      this.innerHTML = '<i class="fas fa-eye"></i>';
    }
  });
});


/* =========================================================
   Courses table: click any non-action cell to enter edit mode
   ========================================================= */
// Courses table: click any non-action cell to enter edit mode
    (function(){
      var tbl = document.getElementById('editableTable');
      if (!tbl) return;
      tbl.addEventListener('click', function(ev){
        var td = ev.target.closest('td');
        var tr = ev.target.closest('tr');
        if (!td || !tr) return;
        if (tr.classList.contains('row-new')) return;   // skip the "new" row
        if (td.classList.contains('actions')) return;   // ignore action column
        var id = tr.dataset.id;
        if (!id) return;
        var url = new URL(location.href);
        url.searchParams.set('edit', id);
        location.href = url.toString();
      });
    })();

    // Import modal (single-step)
    (function(){
      var modal = document.getElementById('importModal');
      if (!modal) return;

      var closeBtn   = document.getElementById('close-import');
      var chooseBtn  = document.getElementById('btn-import-choose');
      var fileInput  = document.getElementById('import-file');
      var fileBadge  = document.getElementById('import-chosen-file');
      var previewBtn = document.getElementById('btn-preview');
      var openBtn    = document.getElementById('open-import');

      function openModal(){
        // wipe server-rendered results on reopen
        modal.querySelectorAll('.import-result').forEach(function(el){ el.remove(); });
        // reset file
        if (fileInput) fileInput.value = '';
        if (fileBadge) fileBadge.textContent = 'No file chosen';
        if (previewBtn){ previewBtn.hidden = true; previewBtn.disabled = true; }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');
      }
      function closeModal(){
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden','true');
      }

      if (openBtn)   openBtn.addEventListener('click', openModal);
      if (closeBtn)  closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

      if (chooseBtn) chooseBtn.addEventListener('click', function(){ if (fileInput) fileInput.click(); });
      if (fileInput) fileInput.addEventListener('change', function(){
        var f = fileInput.files && fileInput.files[0];
        if (fileBadge) fileBadge.textContent = f ? ('Selected: ' + f.name) : 'No file chosen';
        if (previewBtn){ previewBtn.hidden = !f; previewBtn.disabled = !f; }
      });

      // If server rendered preview/import result (validation errors etc.), keep modal open.
      if (modal.classList.contains('is-open') && modal.querySelector('.import-result')) {
        modal.setAttribute('aria-hidden','false');
      }
    })();

    // "Plan Next Year" dropdown on current-year page
    (function(){
      var btn = document.getElementById('btn-plan-next');
      var menu = document.getElementById('menu-plan-next');
      if (!btn || !menu) return;
      btn.addEventListener('click', function(){
        var shown = menu.style.display === 'block';
        menu.style.display = shown ? 'none' : 'block';
      });
      document.addEventListener('click', function(e){
        if (!menu.contains(e.target) && e.target !== btn) menu.style.display = 'none';
      });
    })();

    // Create for next year：
    (function(){
      var btnCreate = document.getElementById('btn-create-next');      // Plan menu: “Create for next year”
      var modal     = document.getElementById('createNextModal');      //  index.php 的小弹窗
      var closeBtn  = document.getElementById('close-create-next');
      var goImport  = document.getElementById('goto-next-and-import'); // 弹窗里的“Import CSV”按钮

      if (!btnCreate || !modal) return; // 当前不是 courses_current 时，这些元素不存在，直接跳过

      function open(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
      function close(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }

      btnCreate.addEventListener('click', open);
      closeBtn && closeBtn.addEventListener('click', close);
      modal.addEventListener('click', function(e){ if (e.target === modal) close(); });

      // 跨页打开 Import：在 URL 上加 open_import=1，下一页读取这个参数后自动点“Import”
      if (goImport){
        goImport.addEventListener('click', function(){
          var url = new URL(goImport.href, location.origin);
          url.searchParams.set('open_import', '1');
          goImport.href = url.toString();
        });
      }

      // 若当前页是 courses_next 且 URL 上有 open_import=1，则自动打开导入弹窗
      (function autoOpenOnNext(){
        var params = new URLSearchParams(location.search);
        if (params.get('open_import') === '1'){
          var openBtn = document.getElementById('open-import');
          if (openBtn) openBtn.click();
        }
      })();
        })();
