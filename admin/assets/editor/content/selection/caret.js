function createCaretManager(area) {
  let savedRange = null;

  function selectionInsideArea(range) {
    if (!range) {
      return false;
    }
    let container = range.commonAncestorContainer;
    if (container === area) {
      return true;
    }
    if (container && container.nodeType === Node.TEXT_NODE) {
      container = container.parentElement;
    }
    return !!(container && area.contains(container));
  }

  function saveSelection() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return;
    }
    const range = selection.getRangeAt(0);
    if (!selectionInsideArea(range)) {
      return;
    }
    savedRange = range.cloneRange();
  }

  function setSavedRange(range) {
    if (!range || !selectionInsideArea(range)) {
      return;
    }
    savedRange = range.cloneRange();
  }

  function restoreSelection() {
    if (!savedRange) {
      area.focus();
      return false;
    }
    const selection = window.getSelection();
    if (!selection) {
      return false;
    }
    selection.removeAllRanges();
    selection.addRange(savedRange);
    area.focus();
    return true;
  }

  function withCaretPreserved(callback) {
    if (typeof callback !== 'function') {
      return;
    }
    let marker = null;
    const selection = window.getSelection();
    let range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
    const hasSelectionInside = range && selectionInsideArea(range);
    if (hasSelectionInside) {
      range = range.cloneRange();
      range.collapse(true);
      marker = document.createElement('span');
      marker.setAttribute('data-ce-marker', '1');
      marker.style.cssText = 'display:inline-block;width:0;height:0;overflow:hidden;';
      range.insertNode(marker);
    }
    callback();
    if (marker && marker.parentNode) {
      const newRange = document.createRange();
      newRange.setStartAfter(marker);
      newRange.collapse(true);
      const sel = window.getSelection();
      if (sel) {
        sel.removeAllRanges();
        sel.addRange(newRange);
      }
      marker.parentNode.removeChild(marker);
    }
    saveSelection();
  }

  function findAnchorAtSelection() {
    const range = savedRange;
    if (!range) {
      return null;
    }
    let container = range.commonAncestorContainer;
    if (container && container.nodeType === Node.TEXT_NODE) {
      container = container.parentElement;
    }
    if (container && container.closest) {
      return container.closest('a');
    }
    return null;
  }

  return {
    saveSelection,
    setSavedRange,
    restoreSelection,
    withCaretPreserved,
    selectionInsideArea,
    findAnchorAtSelection
  };
}

export { createCaretManager };
