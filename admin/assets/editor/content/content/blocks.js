const ALLOWED_BLOCK_TAGS = {
  P: true,
  H2: true,
  H3: true,
  H4: true,
  H5: true,
  H6: true,
  UL: true,
  OL: true,
  BLOCKQUOTE: true,
  PRE: true,
  FIGURE: true
};

function createEmptyParagraph() {
  const paragraph = document.createElement('p');
  paragraph.appendChild(document.createElement('br'));
  return paragraph;
}

function wrapNodeInParagraph(area, node) {
  const paragraph = createEmptyParagraph();
  if (node && node.parentNode === area) {
    area.replaceChild(paragraph, node);
  } else if (node && node.parentNode) {
    node.parentNode.replaceChild(paragraph, node);
  }
  if (node) {
    paragraph.innerHTML = '';
    paragraph.appendChild(node);
  }
  if (!paragraph.innerHTML || paragraph.innerHTML.trim() === '') {
    paragraph.innerHTML = '<br>';
  }
  return paragraph;
}

function ensureBlockPlaceholder(block) {
  if (!block || block.nodeType !== Node.ELEMENT_NODE) {
    return;
  }
  if (block.tagName === 'P' && block.innerHTML.trim() === '') {
    block.innerHTML = '<br>';
  }
  if ((block.tagName === 'UL' || block.tagName === 'OL') && !block.querySelector('li')) {
    const li = document.createElement('li');
    li.appendChild(document.createElement('br'));
    block.appendChild(li);
  }
}

export { ALLOWED_BLOCK_TAGS, createEmptyParagraph, wrapNodeInParagraph, ensureBlockPlaceholder };
