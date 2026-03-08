/**
 * @file
 * Gallery modal behavior.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.gallery = {
    attach: function (context) {
      // Only run once on the document.
      if (context !== document) {
        return;
      }

      // Create modal markup.
      var modal = document.createElement('div');
      modal.className = 'gallery-modal';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-label', 'Image viewer');
      modal.innerHTML =
        '<button class="gallery-modal__close" aria-label="Close">&times;</button>' +
        '<img class="gallery-modal__image" src="" alt="" />';
      document.body.appendChild(modal);

      var modalImage = modal.querySelector('.gallery-modal__image');
      var closeButton = modal.querySelector('.gallery-modal__close');

      function openModal(src, alt) {
        modalImage.src = src;
        modalImage.alt = alt || '';
        modal.classList.add('gallery-modal--open');
        document.body.style.overflow = 'hidden';
        closeButton.focus();
      }

      function closeModal() {
        modal.classList.remove('gallery-modal--open');
        document.body.style.overflow = '';
        modalImage.src = '';
      }

      // Intercept gallery link clicks.
      document.addEventListener('click', function (e) {
        var link = e.target.closest('.gallery-item__link');
        if (link) {
          e.preventDefault();
          openModal(link.href, link.getAttribute('data-gallery-alt'));
        }
      });

      // Close on button click.
      closeButton.addEventListener('click', closeModal);

      // Close on overlay click (not on image).
      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          closeModal();
        }
      });

      // Close on Escape key.
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('gallery-modal--open')) {
          closeModal();
        }
      });
    }
  };

})(Drupal);
