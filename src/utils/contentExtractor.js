/**
 * Extract translatable text from block attributes
 */
function extractTextFromAttrs(attrs, blockPath, blockName) {
  const items = [];
  if (!attrs || typeof attrs !== 'object') {
    return items;
  }

  // Common translatable attribute keys
  const translatableKeys = [
    'content', 'text', 'caption', 'alt', 'title', 'summary', 'citation',
    'value', 'placeholder', 'label',
  ];

  translatableKeys.forEach((key) => {
    if (attrs[key] && typeof attrs[key] === 'string' && attrs[key].trim()) {
      const text = attrs[key].trim();
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = text;
      const plainText = tempDiv.textContent.trim();

      if (plainText) {
        items.push({
          type: 'content',
          text: plainText,
          key: `${blockPath}.attrs.${key}`,
          blockPath: `${blockPath}.attrs.${key}`,
          blockName: blockName || '',
          attrKey: key,
          attrValue: attrs[key],
          blockAttrs: attrs,
          html: attrs[key],
        });
      }
    }
  });

  return items;
}

/**
 * Extract translatable text from blocks recursively
 */
function extractTextFromBlocks(blocks, items, path = []) {
  if (!Array.isArray(blocks)) {
    return items;
  }

  blocks.forEach((block, blockIndex) => {
    const currentPath = [...path, blockIndex];
    const blockPath = currentPath.join('.');
    const blockName = block.blockName || '';

    // Extract text from block attributes first
    if (block.attrs) {
      const attrItems = extractTextFromAttrs(block.attrs, blockPath, blockName);
      items.push(...attrItems);
    }

    // Extract from innerHTML
    if (block.innerHTML) {
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = block.innerHTML;
      const textElements = tempDiv.querySelectorAll('*:not(script, style, noscript, iframe, object, embed, svg)');

      if (textElements.length > 0) {
        let leafIndex = 0;
        textElements.forEach((elem) => {
          const elemText = elem.textContent.trim();
          if (!elemText) return;

          const textChildren = Array.from(elem.children).filter((child) => {
            return !['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg'].includes(
              child.tagName.toLowerCase()
            ) && child.textContent.trim().length > 0;
          });

          if (textChildren.length > 0) return; // Skip parent elements

          const alreadyInAttrs = items.some(
            (item) => item.blockPath && item.blockPath.startsWith(blockPath) && item.text === elemText
          );

          if (!alreadyInAttrs) {
            items.push({
              type: 'content',
              text: elemText,
              key: `${blockPath}.innerHTML[${leafIndex}]`,
              blockPath: `${blockPath}.innerHTML[${leafIndex}]`,
              blockName: blockName,
              blockAttrs: block.attrs || {},
              innerHTML: elem.innerHTML,
              html: elem.innerHTML,
              innerHTMLIndex: leafIndex,
            });
            leafIndex++;
          }
        });
      } else {
        const text = tempDiv.textContent.trim();
        if (text) {
          const alreadyInAttrs = items.some(
            (item) => item.blockPath && item.blockPath.startsWith(blockPath) && item.text === text
          );

          if (!alreadyInAttrs) {
            items.push({
              type: 'content',
              text: text,
              key: `${blockPath}.innerHTML`,
              blockPath: `${blockPath}.innerHTML`,
              blockName: blockName,
              blockAttrs: block.attrs || {},
              innerHTML: block.innerHTML,
              html: block.innerHTML,
            });
          }
        }
      }
    }

    // Extract from innerContent array
    if (Array.isArray(block.innerContent)) {
      block.innerContent.forEach((content, contentIndex) => {
        if (typeof content === 'string' && content.trim()) {
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = content;
          const textElements = tempDiv.querySelectorAll('*:not(script, style, noscript, iframe, object, embed, svg)');

          if (textElements.length > 0) {
            let leafIndex = 0;
            textElements.forEach((elem) => {
              const elemText = elem.textContent.trim();
              if (!elemText) return;

              const textChildren = Array.from(elem.children).filter((child) => {
                return !['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg'].includes(
                  child.tagName.toLowerCase()
                ) && child.textContent.trim().length > 0;
              });

              if (textChildren.length > 0) return;

              const alreadyAdded = items.some(
                (item) => item.blockPath && item.blockPath.startsWith(blockPath) && item.text === elemText
              );

              if (!alreadyAdded) {
                items.push({
                  type: 'content',
                  text: elemText,
                  key: `${blockPath}.innerContent[${contentIndex}][${leafIndex}]`,
                  blockPath: `${blockPath}.innerContent[${contentIndex}][${leafIndex}]`,
                  blockName: blockName,
                  blockAttrs: block.attrs || {},
                  innerContentIndex: contentIndex,
                  innerContentElementIndex: leafIndex,
                  innerContent: elem.innerHTML,
                  html: elem.innerHTML,
                });
                leafIndex++;
              }
            });
          } else {
            const text = tempDiv.textContent.trim();
            if (text) {
              const alreadyAdded = items.some(
                (item) => item.blockPath && item.blockPath.startsWith(blockPath) && item.text === text
              );

              if (!alreadyAdded) {
                items.push({
                  type: 'content',
                  text: text,
                  key: `${blockPath}.innerContent[${contentIndex}]`,
                  blockPath: `${blockPath}.innerContent[${contentIndex}]`,
                  blockName: blockName,
                  blockAttrs: block.attrs || {},
                  innerContentIndex: contentIndex,
                  innerContent: content,
                  html: content,
                });
              }
            }
          }
        }
      });
    }

    // Recursively process innerBlocks
    if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
      extractTextFromBlocks(block.innerBlocks, items, [...currentPath, 'innerBlocks']);
    }
  });

  return items;
}

/**
 * Check if a key contains translatable substring
 */
function subStringsToCheck(strings) {
  const dynamicSubStrings = ['title', 'description', 'editor', 'text', 'content', 'label'];
  const staticSubStrings = [
    'caption', 'heading', 'sub_heading', 'testimonial_content',
    'testimonial_job', 'testimonial_name', 'name',
  ];

  return (
    dynamicSubStrings.some((substring) => strings.toLowerCase().indexOf(substring) !== -1) ||
    staticSubStrings.some((substring) => strings === substring)
  );
}

/**
 * Extract text from Elementor elements recursively
 */
function extractTextFromElementor(elements, items, path = []) {
  if (!Array.isArray(elements)) {
    return items;
  }

  const cssProperties = [
    'content_width', 'title_size', 'font_size', 'margin', 'padding', 'background',
    'border', 'color', 'text_align', 'font_weight', 'font_family', 'line_height',
    'letter_spacing', 'text_transform', 'border_radius', 'box_shadow', 'opacity',
    'width', 'height', 'display', 'position', 'z_index', 'visibility', 'align',
    'max_width', 'content_typography_typography', 'flex_justify_content',
    'title_color', 'description_color', 'email_content',
  ];

  elements.forEach((element, elementIndex) => {
    const currentPath = [...path, elementIndex];
    const elementPath = currentPath.join('.');

    // Extract from settings
    if (element.settings && typeof element.settings === 'object' && element.settings !== null) {
      Object.keys(element.settings).forEach((key) => {
        // Skip CSS properties
        if (cssProperties.some((cssProp) => key.toLowerCase().indexOf(cssProp.toLowerCase()) !== -1)) {
          return;
        }

        const value = element.settings[key];
        if (typeof value === 'string' && value.trim() && subStringsToCheck(key)) {
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = value;
          const plainText = tempDiv.textContent.trim();

          if (plainText) {
            items.push({
              type: 'field',
              text: plainText,
              key: `elementor_${element.id}_${key}`,
              fieldType: `elementor_${element.id}_${key}`,
              elementPath: elementPath,
              elementId: element.id,
              fieldKey: key,
              fieldValue: value,
              html: value,
            });
          }
        }
      });
    }

    // Recursively process elements
    if (Array.isArray(element.elements) && element.elements.length > 0) {
      extractTextFromElementor(element.elements, items, currentPath);
    }
  });

  return items;
}

/**
 * Extract translatable content from post data
 */
export function extractTranslatableContent(postData) {
  const items = [];

  // Add title
  if (postData.title) {
    items.push({
      type: 'title',
      text: postData.title,
      key: 'title',
      html: postData.title,
    });
  }

  // Extract based on editor type
  if (postData.editor_type === 'elementor' && Array.isArray(postData.content)) {
    extractTextFromElementor(postData.content, items);
  } else if (postData.editor_type === 'block' && Array.isArray(postData.content)) {
    extractTextFromBlocks(postData.content, items);
  } else if (postData.content) {
    // Classic editor - extract text from HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = postData.content;
    const textNodes = extractTextNodes(tempDiv);

    textNodes.forEach((text, index) => {
      if (text.trim()) {
        items.push({
          type: 'content',
          text: text.trim(),
          key: `content_${index}`,
          html: text,
        });
      }
    });
  }

  return items;
}

/**
 * Extract text nodes from an element
 */
function extractTextNodes(element) {
  const textNodes = [];
  const walker = document.createTreeWalker(
    element,
    NodeFilter.SHOW_TEXT,
    {
      acceptNode: (node) => {
        // Skip script and style content
        const parent = node.parentElement;
        if (parent && ['script', 'style', 'noscript'].includes(parent.tagName.toLowerCase())) {
          return NodeFilter.FILTER_REJECT;
        }
        return NodeFilter.FILTER_ACCEPT;
      },
    }
  );

  let node;
  while ((node = walker.nextNode())) {
    if (node.textContent.trim()) {
      textNodes.push(node.textContent);
    }
  }

  return textNodes;
}

