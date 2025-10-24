import { createEmptyParagraph } from '../content/blocks.js';

const EMBED_CLASS = 'content-editor-embed';

function isEmbedElement(node) {
  return !!(
    node &&
    node.nodeType === Node.ELEMENT_NODE &&
    node.classList &&
    node.classList.contains(EMBED_CLASS)
  );
}

function ensureEmbedElementAttributes(node) {
  if (!isEmbedElement(node)) {
    return;
  }
  node.classList.add(EMBED_CLASS);
  node.setAttribute('contenteditable', 'false');
  if (!node.hasAttribute('tabindex')) {
    node.setAttribute('tabindex', '0');
  }
  const iframe = node.querySelector('iframe');
  if (iframe) {
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('allowfullscreen', 'true');
    if (!iframe.getAttribute('allow')) {
      iframe.setAttribute(
        'allow',
        'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
      );
    }
  }
  let ratioWrapper = null;
  if (node.children && node.children.length) {
    Array.prototype.some.call(node.children, (child) => {
      if (child.classList && child.classList.contains('ratio')) {
        ratioWrapper = child;
        return true;
      }
      return false;
    });
  }
  if (!ratioWrapper && iframe && iframe.parentElement === node) {
    ratioWrapper = document.createElement('div');
    ratioWrapper.className = 'ratio ratio-16x9';
    node.insertBefore(ratioWrapper, iframe);
    ratioWrapper.appendChild(iframe);
  } else if (ratioWrapper && !/\bratio-\d+x\d+\b/.test(ratioWrapper.className)) {
    ratioWrapper.classList.add('ratio-16x9');
  }
}

function detectEmbedFromUrl(url) {
  if (!url) {
    return null;
  }
  if (typeof URL !== 'function') {
    return null;
  }
  let parsedUrl;
  try {
    parsedUrl = new URL(url);
  } catch (err) {
    return null;
  }
  const host = (parsedUrl.hostname || '').replace(/^www\./i, '').toLowerCase();
  if (!host) {
    return null;
  }
  if (host === 'youtu.be') {
    const ytPath = parsedUrl.pathname.replace(/^\//, '').split('/');
    const ytId = ytPath[0] || '';
    if (ytId) {
      return {
        type: 'youtube',
        embedUrl: `https://www.youtube.com/embed/${ytId}`,
        ratio: '16x9',
        title: 'YouTube video'
      };
    }
  }
  if (host === 'youtube.com' || host === 'm.youtube.com') {
    let videoId = parsedUrl.searchParams.get('v');
    if (!videoId) {
      const pathParts = parsedUrl.pathname.replace(/^\//, '').split('/');
      if (pathParts[0] === 'embed' && pathParts[1]) {
        videoId = pathParts[1];
      } else if (pathParts[0] === 'shorts' && pathParts[1]) {
        videoId = pathParts[1];
      } else if (pathParts[0] === 'live' && pathParts[1]) {
        videoId = pathParts[1];
      }
    }
    if (videoId) {
      return {
        type: 'youtube',
        embedUrl: `https://www.youtube.com/embed/${videoId}`,
        ratio: '16x9',
        title: 'YouTube video'
      };
    }
  }
  if (host === 'vimeo.com' || host === 'player.vimeo.com') {
    const vimeoSegments = parsedUrl.pathname
      .replace(/^\//, '')
      .split('/')
      .filter(Boolean);
    const vimeoId = vimeoSegments.length ? vimeoSegments[vimeoSegments.length - 1] : '';
    if (vimeoId && /^\d+$/.test(vimeoId)) {
      return {
        type: 'vimeo',
        embedUrl: `https://player.vimeo.com/video/${vimeoId}`,
        ratio: '16x9',
        title: 'Vimeo video'
      };
    }
  }
  return null;
}

function createEmbedElement(embedInfo, originalUrl) {
  if (!embedInfo) {
    return null;
  }
  const wrapper = document.createElement('div');
  wrapper.className = EMBED_CLASS;
  wrapper.setAttribute('data-embed-source', originalUrl || '');
  wrapper.setAttribute('contenteditable', 'false');
  wrapper.setAttribute('tabindex', '0');
  const ratio = document.createElement('div');
  ratio.className = `ratio ratio-${embedInfo.ratio || '16x9'}`;
  const iframe = document.createElement('iframe');
  iframe.src = embedInfo.embedUrl;
  iframe.title = embedInfo.title || 'Video';
  iframe.setAttribute('loading', 'lazy');
  iframe.setAttribute('allowfullscreen', 'true');
  iframe.setAttribute(
    'allow',
    'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
  );
  ratio.appendChild(iframe);
  wrapper.appendChild(ratio);
  return wrapper;
}

function extractUrlCandidateFromBlock(block) {
  if (!block || block.tagName !== 'P') {
    return null;
  }
  const meaningfulChildren = [];
  Array.prototype.forEach.call(block.childNodes, (child) => {
    if (child.nodeType === Node.TEXT_NODE && child.textContent.trim() === '') {
      return;
    }
    meaningfulChildren.push(child);
  });
  if (meaningfulChildren.length !== 1) {
    return null;
  }
  const onlyChild = meaningfulChildren[0];
  if (onlyChild.nodeType === Node.TEXT_NODE) {
    const text = onlyChild.textContent.trim();
    if (text && /^https?:\/\//i.test(text)) {
      return text;
    }
    return null;
  }
  if (onlyChild.nodeType === Node.ELEMENT_NODE && onlyChild.tagName === 'A') {
    const href = onlyChild.getAttribute('href') || '';
    const anchorText = onlyChild.textContent.trim();
    if (href && (!anchorText || anchorText === href)) {
      return href;
    }
    if (!href && anchorText && /^https?:\/\//i.test(anchorText)) {
      return anchorText;
    }
  }
  return null;
}

function convertBlocksToEmbeds(area, { markerSelector, paragraphFactory = createEmptyParagraph } = {}) {
  if (!area) {
    return;
  }
  Array.prototype.forEach.call(area.children, (child) => {
    if (isEmbedElement(child)) {
      ensureEmbedElementAttributes(child);
      return;
    }
    if (child.tagName !== 'P') {
      return;
    }
    if (markerSelector && child.querySelector && child.querySelector(markerSelector)) {
      return;
    }
    const urlCandidate = extractUrlCandidateFromBlock(child);
    if (!urlCandidate) {
      return;
    }
    const embedInfo = detectEmbedFromUrl(urlCandidate);
    if (!embedInfo) {
      return;
    }
    const embedElement = createEmbedElement(embedInfo, urlCandidate);
    if (!embedElement) {
      return;
    }
    area.replaceChild(embedElement, child);
    ensureEmbedElementAttributes(embedElement);
    if (!embedElement.previousSibling) {
      area.insertBefore(paragraphFactory(), embedElement);
    }
    if (!embedElement.nextSibling) {
      area.appendChild(paragraphFactory());
    }
  });
}

export {
  EMBED_CLASS,
  isEmbedElement,
  ensureEmbedElementAttributes,
  detectEmbedFromUrl,
  createEmbedElement,
  convertBlocksToEmbeds
};
