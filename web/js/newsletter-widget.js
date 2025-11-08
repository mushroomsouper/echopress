(function(){
  var widgets = document.querySelectorAll('section.newsletter-widget');
  if(!widgets.length){
    return;
  }

  if(!window.__newsletterEnsureRecaptcha){
    window.__newsletterEnsureRecaptcha = (function(){
      var loaderPromise = null;
      var activeKey = null;
      return function(siteKeyValue){
        if(!siteKeyValue){
          return Promise.reject(new Error('Missing reCAPTCHA site key.'));
        }
        if(typeof grecaptcha !== 'undefined' && grecaptcha.execute){
          return Promise.resolve(grecaptcha);
        }
        if(loaderPromise){
          if(activeKey && activeKey !== siteKeyValue){
            console.warn('reCAPTCHA already initialised with a different site key.');
          }
          return loaderPromise;
        }
        activeKey = siteKeyValue;
        loaderPromise = new Promise(function(resolve, reject){
          var script = document.createElement('script');
          script.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(siteKeyValue);
          script.async = true;
          script.defer = true;
          script.onload = function(){
            if(typeof grecaptcha !== 'undefined'){
              resolve(grecaptcha);
            } else {
              reject(new Error('reCAPTCHA failed to initialise.'));
            }
          };
          script.onerror = function(){
            reject(new Error('Unable to load reCAPTCHA.'));
          };
          document.head.appendChild(script);
        });
        return loaderPromise;
      };
    })();
  }

  widgets.forEach(function(widget){
    var form = widget.querySelector('.newsletter-form');
    var prefBtn = widget.querySelector('.pref-next');
    var pop = widget.querySelector('.pref-popover');
    var popInner = pop ? pop.querySelector('.pref-popover-inner') : null;
    var allPosts = pop ? pop.querySelector('input[name="all_posts"]') : null;
    var confirmBtn = form ? form.querySelector('button[type="submit"]') : null;
    var emailInput = form ? form.querySelector('input[type="email"]') : null;

    if(!form || !prefBtn || !pop || !popInner || !allPosts || !confirmBtn || !emailInput){
      return;
    }

    var others = Array.from(pop.querySelectorAll('input[type="checkbox"]')).filter(function(c){
      return c !== allPosts;
    });
    var confirmBtnDefaultText = confirmBtn.textContent;
    var siteKey = widget.getAttribute('data-recaptcha-key') || '';
    if(!siteKey){
      return;
    }

    var ensureReadyPromise = null;
    var isSubmitting = false;

    function restoreConfirm(){
      confirmBtn.disabled = false;
      confirmBtn.classList.remove('loading');
      if(confirmBtn.dataset.loadingText){
        confirmBtn.textContent = confirmBtn.dataset.loadingText;
        delete confirmBtn.dataset.loadingText;
      } else {
        confirmBtn.textContent = confirmBtnDefaultText;
      }
    }

    function primeRecaptcha(){
      if(ensureReadyPromise){
        return ensureReadyPromise;
      }
      confirmBtn.disabled = true;
      confirmBtn.classList.add('loading');
      if(!confirmBtn.dataset.loadingText){
        confirmBtn.dataset.loadingText = confirmBtn.textContent;
      }
      confirmBtn.textContent = 'Loading...';

      ensureReadyPromise = window.__newsletterEnsureRecaptcha(siteKey)
        .then(function(result){
          restoreConfirm();
          return result;
        })
        .catch(function(error){
          restoreConfirm();
          ensureReadyPromise = null;
          throw error;
        });
      return ensureReadyPromise;
    }

    function ensureEmailValid(){
      if (!emailInput) {
        return true;
      }
      var value = emailInput.value.trim();
      if (!value) {
        emailInput.focus();
        if (typeof emailInput.reportValidity === 'function') {
          emailInput.reportValidity();
        }
        return false;
      }
      if (typeof emailInput.checkValidity === 'function' && !emailInput.checkValidity()) {
        if (typeof emailInput.reportValidity === 'function') {
          emailInput.reportValidity();
        }
        return false;
      }
      return true;
    }

    function closePopover(){
      pop.hidden = true;
      pop.classList.remove('show');
      prefBtn.style.display = '';
    }

    prefBtn.addEventListener('click', function(){
      if (!ensureEmailValid()) {
        return;
      }
      pop.hidden = false;
      pop.classList.add('show');
      prefBtn.style.display = 'none';
      primeRecaptcha().catch(function(err){
        console.warn('reCAPTCHA warmup failed', err);
      });
    });

    allPosts.addEventListener('change', function(){
      if(allPosts.checked){
        others.forEach(function(c){ c.checked = true; });
      }
    });

    others.forEach(function(cb){
      cb.addEventListener('change', function(){
        if(!cb.checked){
          allPosts.checked = false;
        }
      });
    });

    form.addEventListener('focusin', function(){
      primeRecaptcha().catch(function(err){
        console.warn('reCAPTCHA warmup failed', err);
      });
    });

    document.addEventListener('click', function (event) {
      if (!pop.classList.contains('show')) {
        return;
      }
      if (popInner.contains(event.target) || prefBtn.contains(event.target)) {
        return;
      }
      closePopover();
    });

    form.addEventListener('submit', function(event){
      if(isSubmitting){
        return;
      }

      event.preventDefault();
      isSubmitting = true;

      primeRecaptcha()
        .then(function(){
          return new Promise(function(resolve, reject){
            try {
              grecaptcha.ready(function(){
                grecaptcha.execute(siteKey, { action: 'newsletter' })
                  .then(resolve)
                  .catch(reject);
              });
            } catch(err){
              reject(err);
            }
          });
        })
        .then(function(token){
          var tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
          if(tokenInput){
            tokenInput.value = token;
          }
          form.submit();
        })
        .catch(function(error){
          console.error('reCAPTCHA error', error);
          alert('Unable to verify reCAPTCHA. Please try again later.');
          isSubmitting = false;
        });
    });
  });
})();
