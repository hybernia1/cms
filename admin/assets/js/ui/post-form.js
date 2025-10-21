export function extractPostPayload(source) {
  if (!source || typeof source !== 'object') {
    return null;
  }

  let candidate = null;
  if (source.data && typeof source.data === 'object' && source.data.post && typeof source.data.post === 'object') {
    candidate = source.data.post;
  } else if (source.post && typeof source.post === 'object') {
    candidate = source.post;
  } else {
    const fallback = {};
    let hasFallback = false;
    if (Object.prototype.hasOwnProperty.call(source, 'postId')) {
      fallback.id = source.postId;
      hasFallback = true;
    } else if (Object.prototype.hasOwnProperty.call(source, 'id')) {
      fallback.id = source.id;
      hasFallback = true;
    }
    if (Object.prototype.hasOwnProperty.call(source, 'type')) {
      fallback.type = source.type;
      hasFallback = true;
    }
    if (Object.prototype.hasOwnProperty.call(source, 'status')) {
      fallback.status = source.status;
      hasFallback = true;
    }
    if (Object.prototype.hasOwnProperty.call(source, 'statusLabel')) {
      fallback.statusLabel = source.statusLabel;
      hasFallback = true;
    }
    if (Object.prototype.hasOwnProperty.call(source, 'actionUrl')) {
      fallback.actionUrl = source.actionUrl;
      hasFallback = true;
    }
    if (Object.prototype.hasOwnProperty.call(source, 'slug')) {
      fallback.slug = source.slug;
      hasFallback = true;
    }
    if (!hasFallback) {
      return null;
    }
    candidate = fallback;
  }

  const result = {};
  if (candidate) {
    if (Object.prototype.hasOwnProperty.call(candidate, 'id') || Object.prototype.hasOwnProperty.call(candidate, 'postId')) {
      const idValue = Object.prototype.hasOwnProperty.call(candidate, 'id') ? candidate.id : candidate.postId;
      if (idValue !== undefined && idValue !== null && String(idValue).trim() !== '') {
        result.id = String(idValue);
      }
    }
    if (Object.prototype.hasOwnProperty.call(candidate, 'type') && candidate.type !== undefined && candidate.type !== null) {
      result.type = String(candidate.type);
    }
    if (Object.prototype.hasOwnProperty.call(candidate, 'status') && candidate.status !== undefined && candidate.status !== null) {
      result.status = String(candidate.status);
    }
    if (Object.prototype.hasOwnProperty.call(candidate, 'statusLabel') && candidate.statusLabel !== undefined && candidate.statusLabel !== null) {
      result.statusLabel = String(candidate.statusLabel);
    }
    if (Object.prototype.hasOwnProperty.call(candidate, 'actionUrl') && candidate.actionUrl !== undefined && candidate.actionUrl !== null) {
      result.actionUrl = String(candidate.actionUrl);
    }
    if (Object.prototype.hasOwnProperty.call(candidate, 'slug')) {
      const slugValue = candidate.slug;
      result.slug = slugValue === undefined || slugValue === null ? '' : String(slugValue);
    }
  }

  return Object.keys(result).length ? result : null;
}

export function applyPostPayloadToForm(form, postPayload, options) {
  if (!form || !postPayload || typeof postPayload !== 'object') {
    return { actionUrl: '', postId: '' };
  }

  const settings = options || {};
  const actionUrlFromPayload = typeof postPayload.actionUrl === 'string' ? postPayload.actionUrl : '';
  if (actionUrlFromPayload) {
    form.setAttribute('action', actionUrlFromPayload);
  }

  let newPostId = '';
  if (postPayload.id !== undefined && postPayload.id !== null && String(postPayload.id).trim() !== '') {
    newPostId = String(postPayload.id).trim();
  }

  const statusInput = settings.statusInput || form.querySelector('input[name="status"]');
  if (statusInput && postPayload.status) {
    statusInput.value = String(postPayload.status);
  }

  const statusLabelEl = settings.statusLabelEl || form.querySelector('#status-current-label');
  if (statusLabelEl && postPayload.statusLabel) {
    statusLabelEl.textContent = String(postPayload.statusLabel);
  }

  const slugInput = settings.slugInput || form.querySelector('input[name="slug"]');
  if (slugInput && Object.prototype.hasOwnProperty.call(postPayload, 'slug')) {
    const slugValue = postPayload.slug !== undefined && postPayload.slug !== null
      ? String(postPayload.slug)
      : '';
    slugInput.value = slugValue;
  }

  if (postPayload.type) {
    form.setAttribute('data-post-type', String(postPayload.type));
  }

  return {
    actionUrl: actionUrlFromPayload,
    postId: newPostId
  };
}
