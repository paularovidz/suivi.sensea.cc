// Script client-side pour les liens obfusqués
document.addEventListener('DOMContentLoaded', function() {
  // Liens avec data-lkb (ouvre dans un nouvel onglet)
  let lkb = document.querySelectorAll('[data-lkb]');
  for (let i = 0; i < lkb.length; i++) {
      lkb[i].addEventListener('click', function (event) {
          event.preventDefault();
          window.open(window.atob( this.getAttribute('data-lkb') ), '_blank'); 
      });
  }

  // Liens avec data-lk (redirection simple)
  let lk = document.querySelectorAll('[data-lk]');
  for (let i = 0; i < lk.length; i++) {
      lk[i].addEventListener('click', function (event) {
          event.preventDefault();
          window.location.href = this.getAttribute('data-lk'); 
      });
  }

  // Liens avec data-lko (redirection avec base64)
  let lko = document.querySelectorAll('[data-lko]');
  if(lko.length) {
      for (let i = 0; i < lko.length; i++) {
          lko[i].addEventListener('click', function (event) {
              event.preventDefault();
              window.location.href = window.atob(this.getAttribute('data-lko')); 
          });
      }
  }

  // Liens avec data-lki (redirection interne avec base64)
  let lki = document.querySelectorAll('[data-lki]');
  if(lki.length) {
      for (let i = 0; i < lki.length; i++) {
          lki[i].addEventListener('click', function (event) {
              event.preventDefault();
              window.location.href = window.atob(this.getAttribute('data-lki')); 
          });
      }
  }
});
  