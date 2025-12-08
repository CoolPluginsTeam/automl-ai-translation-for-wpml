/**
 * Extract page ID from checkbox ID
 */
export function extractPageId(row) {
  const checkbox = row.querySelector('td.checkboxes input[type="checkbox"]');
  if (!checkbox || !checkbox.id) {
    return null;
  }
  return checkbox.id.replace(/^\D+/, '');
}

/**
 * Ensure every .action-row has a "Translate by Google" button
 */
export function ensureGoogleButtons(root) {
  const actionRows = root.querySelectorAll('.action-row');
  
  actionRows.forEach((actionRow) => {
    if (actionRow.querySelector('.cp-wpml-auto-translate-btn')) {
      return; // Button already exists
    }

    const btn = document.createElement('button');
    btn.className = 'wpml-button base-btn text-button cp-wpml-auto-translate-btn';
    btn.textContent = 'Translate by Google';

    const separator = document.createElement('span');
    separator.className = 'link-separator';
    separator.textContent = '|';

    actionRow.appendChild(separator);
    actionRow.appendChild(btn);

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const row = btn.closest('tr');
      const pageId = extractPageId(row);

      if (!pageId) {
        alert('Could not detect page ID for this row.');
        return;
      }

      // Trigger custom event for React to handle
      window.dispatchEvent(new CustomEvent('wpml-translate-request', {
        detail: { postIds: [pageId] },
      }));
    });
  });
}

/**
 * Initialize bulk translate button
 */
export function initBulkTranslateButton(onClick) {
  const section = document.querySelector(
    '.wpml-dashboard__SelectionSection .wpml-global-filter-wrapper .wpml-global-filter .wpml-flex-space-between'
  );

  if (!section) {
    return;
  }

  // Check if button already exists
  if (section.querySelector('#cp-wpml-auto-translate-bulk-translate')) {
    return;
  }

  const translateBtn = document.createElement('button');
  translateBtn.type = 'button';
  translateBtn.className = 'button button-primary';
  translateBtn.id = 'cp-wpml-auto-translate-bulk-translate';
  translateBtn.textContent = 'Translate with Google';

  translateBtn.addEventListener('click', () => {
    const selectedPageIds = Array.from(
      document.querySelectorAll('.wpml-item-type-element-list table tbody tr')
    )
      .filter((row) => {
        const checkbox = row.querySelector('td.checkboxes input[type="checkbox"][aria-checked="true"]');
        return checkbox !== null;
      })
      .map((row) => extractPageId(row))
      .filter((id) => id !== null && id !== '');

    if (!selectedPageIds.length) {
      alert('Please select at least one post to translate.');
      return;
    }

    if (onClick) {
      onClick(selectedPageIds);
    } else {
      // Fallback: trigger custom event
      window.dispatchEvent(new CustomEvent('wpml-translate-request', {
        detail: { postIds: selectedPageIds },
      }));
    }
  });

  section.appendChild(translateBtn);
}

/**
 * Initialize row action translate button
 */
export function initRowActionTranslateButton(onClick) {
  document.addEventListener('click', (e) => {
    if (e.target.classList.contains('cp-wpml-row-translate-btn')) {
      e.preventDefault();
      const postId = e.target.getAttribute('data-post-id');

      if (!postId) {
        alert('Could not detect post ID.');
        return;
      }

      if (onClick) {
        onClick(postId);
      } else {
        // Fallback: trigger custom event
        window.dispatchEvent(new CustomEvent('wpml-translate-request', {
          detail: { postIds: [postId] },
        }));
      }
    }
  });
}

