(function(){
  const form = document.querySelector('form');
  if(!form) return;
  const all = form.querySelector('input[name="all_posts"]');
  if(!all) return;
  const others = Array.from(form.querySelectorAll('input[type="checkbox"]')).filter(cb => cb !== all);

  // Ensure initial state follows the same rule
  if(all.checked){
    others.forEach(c => c.checked = true);
  }

  all.addEventListener('change', () => {
    if(all.checked){
      others.forEach(c => c.checked = true);
    }
  });
  others.forEach(cb => {
    cb.addEventListener('change', () => {
      if(!cb.checked){
        all.checked = false;
      }
    });
  });
})();
