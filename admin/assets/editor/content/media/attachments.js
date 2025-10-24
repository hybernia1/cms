function parseMediaIds(html) {
  if (!html) {
    return [];
  }
  const container = document.createElement('div');
  container.innerHTML = html;
  const imgs = container.querySelectorAll ? container.querySelectorAll('img[data-media-id]') : [];
  const collected = [];
  Array.prototype.forEach.call(imgs, (img) => {
    const raw = img.getAttribute('data-media-id');
    if (!raw) {
      return;
    }
    const id = parseInt(raw, 10);
    if (!Number.isNaN(id) && id > 0) {
      collected.push(id);
    }
  });
  return Array.from(new Set(collected));
}

function createAttachmentsManager({ input } = {}) {
  let attachments = new Set();

  function refreshFromHtml(html) {
    const ids = parseMediaIds(html || '');
    attachments = new Set(ids);
    if (input) {
      input.value = ids.length ? JSON.stringify(ids) : '';
    }
    return attachments;
  }

  function getAttachments() {
    return Array.from(attachments);
  }

  return { refreshFromHtml, getAttachments };
}

export { parseMediaIds, createAttachmentsManager };
