 Joomla = window.Joomla || {};

(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const createBtn = document.querySelector('.button-autoassoc');
    const changeStatusBtn = document.querySelector('.button-status-group');
    const modal = document.querySelector('#autoassocModal');

    console.log(changeStatusBtn);

    createBtn.addEventListener('click', () => {
      checkSelectedItems();
    });

  function checkSelectedItems() {
    const associationsList = document.querySelector('#associationsList');
    const modalBodyIframe = modal.querySelector('iframe');
    let tdId;
    let associationsListRows = [].slice.call(associationsList.querySelectorAll('tbody tr'));

    if (associationsList) {
      associationsListRows.forEach((el) => {
        const checkedBox = el.querySelectorAll('input[type=checkbox]')[0];

        if (checkedBox.checked) {
          const parentTr = checkedBox.parentNode;
           tdId = parentTr.querySelector('input').value;
        }
      });

      modal.dataset.url = modal.dataset.url.replace(/(?=id\=)(.*)(?=&)/g,"id="+ tdId);
      modal.dataset.iframe = modal.dataset.iframe.replace(/(?=id\=)(.*)(?=&)/g,"id="+ tdId);
      createBtn.value = createBtn.value.replace(/(?=id\=)(.*)(?=&)/g,"id="+ tdId);

      if(modalBodyIframe){
        modalBodyIframe.src = modalBodyIframe.src.replace(/(?=id\=)(.*)(?=&)/g,"id="+ tdId);
      }
    }

  }

  });
})();

