/**
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
((document, submitForm) => {
  'use strict';

  // Selectors used by this script
  const buttonDataSelector = 'data-submit-task';
  const formId = 'adminForm';
  const formAssoc = 'autoassocModal';

  /**
   * Submit the task
   * @param task
   */
  const submitTask = (task) => {
    const form = document.getElementById(formAssoc);
    const formParent = document.getElementById(formId);

    if (form && task === 'autoassoc.autocreate') {

      const iframe = document.querySelector('.iframe');
      const innerdoc = iframe.contentDocument || iframe.contentWindow.document;

      console.log(iframe);
      console.log(innerdoc);
      console.log("-------------------------------");
      console.log(innerdoc.querySelectorAll("td.row-selected input[type='checkbox']"));
      const checkedBoxes = [].slice.call(innerdoc.querySelectorAll("td.row-selected input[type='checkbox']"));
      //TODO where are the assocLanguages set? Now they are not set, so it can't work to save
      const assocLanguages = innerdoc.querySelector("input[name='assocLanguages']");
      const languageIds = [];

      console.log(checkedBoxes);
      console.log(assocLanguages);

      //TODO the checkboxes aren't correct, it gets the checkboxes from the listview and not from the model
      if (checkedBoxes.length) {
        checkedBoxes.forEach((box) => {
          console.log(box);
          console.log(box.value);
          languageIds.push(box.value);
        });
      }

      console.log("test");

      if (languageIds.length) {
        languageIds.forEach((languageId, index) => {
          console.log("test");
          if (index === 0) {
            assocLanguages.value = languageId;
          } else {
            assocLanguages.value += (`:${languageId}`);
          }
        });
      }

      submitForm(task, formParent);
    }
  };

  // Register events
  document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('autoassoc-submit-button-id');

    if (button) {
      button.addEventListener('click', (e) => {
        const task = e.target.getAttribute(buttonDataSelector);
        console.log("hier: " + task);
        submitTask(task);
      });
    }

  });

})(document, Joomla.submitform);
