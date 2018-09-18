/**
 * @copyright  Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

(() => {

    document.addEventListener('DOMContentLoaded', function () {
        const publishBtn = document.getElementById('toolbar-publish').getElementsByClassName("button-publish")[0];
        const unpublishBtn = document.getElementById('toolbar-unpublish').getElementsByClassName("button-unpublish")[0];
        const archiveBtn = document.getElementById('toolbar-archive').getElementsByClassName("button-archive")[0];
        const trashBtn = document.getElementById('toolbar-trash').getElementsByClassName("button-trash")[0];

        const articleListRow = document.getElementById('articleList').getElementsByTagName('tbody tr');
        const articlesStatus = [].slice.call(document.querySelectorAll('article-status'));

        publishBtn.setAttribute('disabled', true);
        unpublishBtn.setAttribute('disabled', true);
        archiveBtn.setAttribute('disabled', true);
        trashBtn.setAttribute('disabled', true);

// check row, if row is selected
        // not working yet:
        console.log(articleListRow);
        for (let i = 0, l = articlesStatus.length; l > i; i += 1) {
            // Listen for click event
            articleListRow[i].addEventListener('click', function() {
                console.log("test");

            });
        }
    });
})();