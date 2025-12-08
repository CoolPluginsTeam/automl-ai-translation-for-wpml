import React, { useEffect, useState, useCallback } from 'react';
import TranslationRow from './TranslationRow';
import { extractTranslatableContent } from '../utils/contentExtractor';
import { saveTranslation } from '../api/translations';

const TranslationTable = ({ postData, targetLang, postId, onSaveButtonStateChange, onSave }) => {
  const [translationItems, setTranslationItems] = useState([]);
  const [translationsMap, setTranslationsMap] = useState({});
  const [hasChanges, setHasChanges] = useState(false);

  useEffect(() => {
    if (postData) {
      const items = extractTranslatableContent(postData);
      setTranslationItems(items);
      
      // Initialize translations map
      const initialMap = {};
      items.forEach((item) => {
        initialMap[item.key] = item.text;
      });
      setTranslationsMap(initialMap);
    }
  }, [postData]);

  const handleTranslationChange = useCallback((key, translatedText) => {
    setTranslationsMap((prev) => ({
      ...prev,
      [key]: translatedText,
    }));
    setHasChanges(true);
  }, []);

  // Helper function to extract text content from HTML string
  const extractTextFromHtml = useCallback((html) => {
    if (!html) return '';
    // Create a temporary element to extract text
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent?.trim() || temp.innerText?.trim() || '';
  }, []);

  useEffect(() => {
    // Check if any translations have been modified
    const hasModifiedTranslations = translationItems.some((item) => {
      const currentTranslation = translationsMap[item.key] || '';
      if (!currentTranslation) {
        return false;
      }
      
      // Compare text content (strip HTML for comparison)
      const currentText = extractTextFromHtml(currentTranslation);
      const sourceText = extractTextFromHtml(item.html || item.text);
      
      // Translation is considered changed if text content differs
      return currentText && currentText !== sourceText;
    });
    setHasChanges(hasModifiedTranslations);
    onSaveButtonStateChange(hasModifiedTranslations);
  }, [translationsMap, translationItems, onSaveButtonStateChange, extractTextFromHtml]);

  const resetGoogleTranslate = () => {
    try {
      // Find container element with ID ':1.container' (jQuery selector: $('#\\:1\\.container'))
      const container = document.getElementById(':1.container') || 
                       document.querySelector('#\\:1\\.container');
      
      if (container) {
        // jQuery .contents() gets iframe document if container is an iframe
        // or gets all child nodes if it's a regular element
        let targetDoc = null;
        
        // Check if container is an iframe
        if (container.tagName === 'IFRAME') {
          try {
            targetDoc = container.contentDocument || container.contentWindow?.document;
          } catch (e) {
            // Cross-origin restriction
          }
        } else {
          // If not iframe, look for iframe inside container
          const iframe = container.querySelector('iframe');
          if (iframe) {
            try {
              targetDoc = iframe.contentDocument || iframe.contentWindow?.document;
            } catch (e) {
              // Cross-origin restriction
            }
          }
        }
        
        // Find restore button (jQuery: .find('#\\:1\\.restore'))
        if (targetDoc) {
          const restoreBtn = targetDoc.getElementById(':1.restore') || 
                            targetDoc.querySelector('#\\:1\\.restore');
          if (restoreBtn) {
            restoreBtn.click(); // jQuery: .click()
            return;
          }
        }
      }
    } catch (error) {
      // Silently fail if Google Translate widget is not available
    }
  };

  const handleSave = useCallback(async () => {
    if (!hasChanges) {
      return;
    }

    const saveBtn = document.getElementById('cp-wpml-auto-translate-translation-save');
    const saveBtnFooter = document.getElementById('cp-wpml-auto-translate-translation-save-footer');
    const originalText = saveBtn?.textContent || 'Update Content';

    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';
    }
    if (saveBtnFooter) {
      saveBtnFooter.disabled = true;
      saveBtnFooter.textContent = 'Saving...';
    }

    try {
        // Collect translations from translationItems (matching old jQuery approach)
      const translations = {};
      translationItems.forEach((item, index) => {
        const translation = translationsMap[item.key];
        if (translation && translation !== item.text) {
          translations[index] = {
            item: item,
            text: translation,
            field_name: item.field_name || null,
          };
        }
      });

      // Get translated title
      const translatedTitle = translationsMap['title'] || postData.title || '';

      // Prepare translated content using original_content (like old jQuery code)
      const originalContent = postData.original_content || postData.content;
      const translatedContent = prepareTranslatedContent(postData, translations, originalContent);
      const translatedFields = prepareTranslatedFields(postData, translations);

      const translationsMapForPHP = {};
      Object.keys(translations).forEach((index) => {
        const translation = translations[index];
        if (translation.field_name) {
          translationsMapForPHP[translation.field_name] = translation.text;
        }
      });

      const response = await saveTranslation(
        postId,
        targetLang,
        translatedTitle,
        translatedContent,
        translatedFields,
        translationsMapForPHP,
        postData.editor_type
      );

      if (response.success) {
        // Reset Google Translate widget before closing
        // Add small delay to ensure iframe is accessible
        setTimeout(() => {
          resetGoogleTranslate();
        }, 100);
        
        // Animate popup closing
        const popup = document.getElementById('cp-wpml-auto-translate-translation-popup');
        if (popup) {
          popup.style.opacity = '0';
          popup.style.transition = 'opacity 0.5s';
          setTimeout(() => {
            onSave();
          }, 500);
        } else {
          onSave();
        }
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        alert(`Failed to save translation: ${response.data?.msg || 'Unknown error'}`);
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.textContent = originalText;
        }
        if (saveBtnFooter) {
          saveBtnFooter.disabled = false;
          saveBtnFooter.textContent = originalText;
        }
      }
    } catch (error) {
      alert('AJAX error while saving translation.');
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
      }
      if (saveBtnFooter) {
        saveBtnFooter.disabled = false;
        saveBtnFooter.textContent = originalText;
      }
    }
  }, [hasChanges, translationsMap, postData, postId, targetLang, onSave, translationItems]);

  // Expose save handler
  useEffect(() => {
    const saveBtn = document.getElementById('cp-wpml-auto-translate-translation-save');
    const saveBtnFooter = document.getElementById('cp-wpml-auto-translate-translation-save-footer');
    
    const handleSaveClick = async (e) => {
      e.preventDefault();
      if (hasChanges) {
        await handleSave();
        
      }
    };

    if (saveBtn) {
      saveBtn.addEventListener('click', handleSaveClick);
    }
    if (saveBtnFooter) {
      saveBtnFooter.addEventListener('click', handleSaveClick);
    }

    return () => {
      if (saveBtn) {
        saveBtn.removeEventListener('click', handleSaveClick);
      }
      if (saveBtnFooter) {
        saveBtnFooter.removeEventListener('click', handleSaveClick);
      }
    };
  }, [hasChanges, handleSave]);

  const prepareTranslatedContent = (postData, translations, originalContent) => {
    if (postData.editor_type === 'elementor') {
      // Use original_content like old jQuery code
      if (!originalContent || !Array.isArray(originalContent)) {
        return '';
      }

      // Deep clone Elementor elements to avoid modifying original
      let translatedElementor = JSON.parse(JSON.stringify(originalContent));

      // Update Elementor elements with translated text (matching old jQuery logic)
      Object.keys(translations).forEach((index) => {
        const translation = translations[index];
        if (translation.item && translation.item.text) {
          // If we have elementPath, use it to directly update the value
          if (translation.item.elementPath) {
            translatedElementor = updateElementorByPath(
              translatedElementor,
              translation.item.elementPath,
              translation.text
            );
          } else {
            // Fallback to text matching if no elementPath
            const originalValue = translation.item.settingValue || translation.item.text;
            translatedElementor = updateElementorWithTranslation(
              translatedElementor,
              originalValue,
              translation.text
            );
          }
        }
      });

      return JSON.stringify(translatedElementor);
    } else if (postData.editor_type === 'block') {
      // Use original_content like old jQuery code
      if (!originalContent || !Array.isArray(originalContent)) {
        return '';
      }

      // Deep clone blocks to avoid modifying original
      let translatedBlocks = JSON.parse(JSON.stringify(originalContent));

      // Update blocks with translated text (matching old jQuery logic)
      Object.keys(translations).forEach((index) => {
        const translation = translations[index];
        if (translation.item && translation.item.text) {
          // Use the HTML if available, otherwise use text
          const originalContent = translation.item.html || translation.item.innerHTML || translation.item.text;
          const translatedContent = translation.text;

          translatedBlocks = updateBlockWithTranslation(
            translatedBlocks,
            translation.item.text,
            translatedContent,
            originalContent
          );
        }
      });

      return JSON.stringify(translatedBlocks);
    } else {
      // Classic editor - use original_content
      const originalContentStr = typeof originalContent === 'string' ? originalContent : '';
      return updateClassicContent(originalContentStr, translations);
    }
  };

  const prepareTranslatedFields = () => {
    return [];
  };


  const updateClassicContent = (content, translations) => {
    if (!content) return content;
    
    let updatedContent = content;
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = content;
    
    // Replace each translation in the original content while preserving HTML structure
    // (matching old jQuery logic)
    Object.keys(translations).forEach((index) => {
      const translation = translations[index];
      if (translation.item && translation.item.text && translation.text) {
        const originalText = translation.item.text.trim();
        let translatedText = translation.text;
        const originalHtml = translation.item.html || translation.item.text;
        
        // Check if we have HTML structure to match
        const hasOriginalHtml = originalHtml && /<[^>]+>/.test(originalHtml) && originalHtml !== originalText;
        
        if (hasOriginalHtml) {
          // Try to find element by HTML structure first (like old jQuery code)
          let replaced = false;
          const normalizedOriginalHtml = originalHtml.replace(/\s+/g, ' ').trim();
          
          // Process from innermost to outermost to match leaf elements first
          const allElements = Array.from(tempDiv.querySelectorAll('*:not(script, style, noscript, iframe, object, embed, svg)')).reverse();
          
          for (const elem of allElements) {
            if (replaced) break;
            
            const elemText = elem.textContent?.trim() || '';
            const elemHtml = elem.innerHTML;
            const normalizedElemHtml = elemHtml.replace(/\s+/g, ' ').trim();
            
            // Match by HTML structure if available
            if (normalizedElemHtml === normalizedOriginalHtml && elemText === originalText) {
              // Check if translated text contains HTML
              const translatedDiv = document.createElement('div');
              translatedDiv.innerHTML = translatedText;
              const translatedHasHTML = translatedDiv.children.length > 0 || translatedText !== translatedDiv.textContent;
              
              if (translatedHasHTML) {
                // Replace with translated HTML structure
                elem.outerHTML = translatedText;
              } else {
                // Plain text - replace element's content but preserve structure
                elem.innerHTML = translatedText;
              }
              replaced = true;
            }
          }
          
          if (!replaced) {
            // Fallback to text-based replacement
            updatedContent = replaceTextInHTML(updatedContent, originalText, translatedText);
            tempDiv.innerHTML = updatedContent;
          }
        } else {
          // No HTML structure, use text-based replacement
          // If translated text contains HTML, extract just the text
          const translatedDiv = document.createElement('div');
          translatedDiv.innerHTML = translatedText;
          const translatedHasHTML = translatedDiv.children.length > 0 || translatedText !== translatedDiv.textContent;
          
          if (translatedHasHTML) {
            // Extract just the text content, preserving the HTML structure of the original
            translatedText = translatedDiv.textContent || translatedText;
          }
          
          updatedContent = replaceTextInHTML(updatedContent, originalText, translatedText);
          tempDiv.innerHTML = updatedContent;
        }
      }
    });
    
    // Get final content from tempDiv
    return tempDiv.innerHTML || updatedContent;
  };

  // Helper function to replace text in HTML (matching old jQuery logic)
  const replaceTextInHTML = (html, originalText, translatedText) => {
    if (!html || typeof html !== 'string') {
      return html;
    }

    const cleanTranslatedText = translatedText;
    const originalTrimmed = originalText.trim();
    
    // Check if original HTML contains the text
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    const htmlText = tempDiv.textContent || '';
    
    // If the text doesn't exist in the HTML, return original
    if (htmlText.indexOf(originalTrimmed) === -1) {
      return html;
    }
    
    // Check if translated text contains HTML
    const translatedDiv = document.createElement('div');
    translatedDiv.innerHTML = cleanTranslatedText;
    const translatedHasHTML = translatedDiv.children.length > 0 || cleanTranslatedText !== translatedDiv.textContent;
    
    // Check if original HTML has the same structure as what we're looking for
    const originalHasHTML = /<[^>]+>/.test(html);
    
    // Strategy: Try to find leaf elements (elements with no text-containing children)
    if (originalHasHTML) {
      const allElements = tempDiv.querySelectorAll('*:not(script, style, noscript, iframe, object, embed, svg)');
      let found = false;
      
      for (const elem of allElements) {
        if (found) break;
        
        const elemText = elem.textContent?.trim() || '';
        const elemHtml = elem.innerHTML;
        
        // Check if this element's text matches exactly
        if (elemText === originalTrimmed) {
          // Check if this element has no child elements with text (leaf element)
          const textChildren = Array.from(elem.children).filter((child) => {
            return !['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg'].includes(child.tagName.toLowerCase()) &&
                   (child.textContent?.trim() || '').length > 0;
          });
          
          if (textChildren.length === 0) {
            // Replace the entire element's HTML
            if (translatedHasHTML) {
              elem.innerHTML = cleanTranslatedText;
            } else {
              elem.textContent = cleanTranslatedText;
            }
            found = true;
          }
        }
      }
      
      if (found) {
        return tempDiv.innerHTML;
      }
    }
    
    // Fallback: Direct string replacement
    const escaped = originalTrimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return html.replace(new RegExp(escaped, 'g'), cleanTranslatedText);
  };


  // Recursively find and update block content with translated text (matching old jQuery logic)
  const updateBlockWithTranslation = (blocks, originalText, translatedText, originalHtml) => {
    if (!Array.isArray(blocks)) {
      return blocks;
    }

    return blocks.map((block) => {
      const updatedBlock = JSON.parse(JSON.stringify(block)); // Deep clone

      // Update block attributes (attrs) - this is where most block text is stored
      if (updatedBlock.attrs && typeof updatedBlock.attrs === 'object') {
        const cleanTranslatedText = translatedText;
        const translatableKeys = ['content', 'text', 'caption', 'alt', 'title', 'summary', 'citation', 'value', 'placeholder', 'label'];
        
        translatableKeys.forEach((key) => {
          if (updatedBlock.attrs[key] && typeof updatedBlock.attrs[key] === 'string') {
            const attrValue = updatedBlock.attrs[key];
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = attrValue;
            const attrText = tempDiv.textContent?.trim() || '';
            const originalTrimmed = originalText.trim();
            
            // If the attribute text matches exactly, replace it
            if (attrText === originalTrimmed) {
              // Check if attribute contains HTML
              if (attrValue !== attrText) {
                // Contains HTML - use HTML replacement
                updatedBlock.attrs[key] = replaceTextInHTML(attrValue, originalText, cleanTranslatedText);
              } else {
                // Plain text - simple replacement
                updatedBlock.attrs[key] = cleanTranslatedText;
              }
            } else if (attrText.indexOf(originalTrimmed) !== -1) {
              // Partial match - replace text within the attribute
              if (attrValue !== attrText) {
                updatedBlock.attrs[key] = replaceTextInHTML(attrValue, originalText, cleanTranslatedText);
              } else {
                const escaped = originalTrimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                updatedBlock.attrs[key] = attrValue.replace(new RegExp(escaped, 'g'), cleanTranslatedText);
              }
            }
          }
        });
      }

      // Update innerHTML if it contains the original text
      if (updatedBlock.innerHTML) {
        const originalInnerHTML = updatedBlock.innerHTML;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalInnerHTML;
        const htmlText = tempDiv.textContent || '';
        const originalTrimmed = originalText.trim();
        const cleanTranslatedText = translatedText;
        
        // Check if innerHTML contains the exact text we're looking for
        if (htmlText.indexOf(originalTrimmed) !== -1) {
          let replaced = false;
          
          // If we have original HTML, try to match by HTML content first
          if (originalHtml && /<[^>]+>/.test(originalHtml)) {
            const normalizedOriginalHtml = originalHtml.replace(/\s+/g, ' ').trim();
            
            // Try to find an element whose HTML matches the original
            const allElements = tempDiv.querySelectorAll('*:not(script, style, noscript, iframe, object, embed, svg)');
            for (const elem of allElements) {
              if (replaced) break;
              
              const elemText = elem.textContent?.trim() || '';
              const elemHtml = elem.innerHTML;
              const normalizedElemHtml = elemHtml.replace(/\s+/g, ' ').trim();
              
              // If the HTML content matches (ignoring whitespace), replace it
              if (normalizedElemHtml === normalizedOriginalHtml && elemText === originalTrimmed) {
                elem.innerHTML = cleanTranslatedText;
                replaced = true;
                break;
              }
            }
            
            // Also check if the entire innerHTML matches (normalized)
            if (!replaced) {
              const normalizedInnerHTML = originalInnerHTML.replace(/\s+/g, ' ').trim();
              if (normalizedInnerHTML === normalizedOriginalHtml) {
                updatedBlock.innerHTML = cleanTranslatedText;
                replaced = true;
              }
            }
          }
          
          // If HTML matching didn't work, try text-based matching
          if (!replaced) {
            // First try to find leaf elements (elements with no text-containing children)
            const allElements = tempDiv.querySelectorAll('*:not(script, style, noscript, iframe, object, embed, svg)');
            for (const elem of allElements) {
              if (replaced) break;
              
              const elemText = elem.textContent?.trim() || '';
              
              if (elemText === originalTrimmed) {
                // Check if this element has no child elements with text (leaf element)
                const textChildren = Array.from(elem.children).filter((child) => {
                  return !['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg'].includes(child.tagName.toLowerCase()) &&
                         (child.textContent?.trim() || '').length > 0;
                });
                
                if (textChildren.length === 0) {
                  // Check if translated text contains HTML
                  const translatedDiv = document.createElement('div');
                  translatedDiv.innerHTML = cleanTranslatedText;
                  const translatedHasHTML = translatedDiv.children.length > 0 || cleanTranslatedText !== translatedDiv.textContent;
                  
                  if (translatedHasHTML) {
                    elem.innerHTML = cleanTranslatedText;
                  } else {
                    elem.textContent = cleanTranslatedText;
                  }
                  replaced = true;
                  break;
                }
              }
            }
          }
          
          if (replaced) {
            // Get updated HTML from tempDiv if we modified it
            const updatedHtml = tempDiv.innerHTML;
            if (updatedHtml !== originalInnerHTML) {
              updatedBlock.innerHTML = updatedHtml;
            }
          } else {
            // Fallback to general replacement using replaceTextInHTML
            updatedBlock.innerHTML = replaceTextInHTML(
              originalInnerHTML,
              originalText,
              cleanTranslatedText
            );
          }
        }
      }

      // Update innerContent array
      if (Array.isArray(updatedBlock.innerContent)) {
        updatedBlock.innerContent = updatedBlock.innerContent.map((content) => {
          if (typeof content === 'string') {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            const contentText = tempDiv.textContent?.trim() || '';
            const originalTrimmed = originalText.trim();
            const cleanTranslatedText = translatedText;
            
            // Check if this innerContent string contains the text we're looking for
            if (contentText.indexOf(originalTrimmed) !== -1) {
              let replaced = false;
              
              // Process from innermost to outermost to match leaf elements first
              const allElements = Array.from(tempDiv.querySelectorAll('*:not(script, style, noscript, iframe, object, embed, svg)')).reverse();
              
              for (const elem of allElements) {
                if (replaced) break;
                
                const elemText = elem.textContent?.trim() || '';
                
                if (elemText === originalTrimmed) {
                  // Check if this is a leaf element
                  const textChildren = Array.from(elem.children).filter((child) => {
                    return !['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg'].includes(child.tagName.toLowerCase()) &&
                           (child.textContent?.trim() || '').length > 0;
                  });
                  
                  if (textChildren.length === 0) {
                    // Check if translated text contains HTML tags
                    const translatedDiv = document.createElement('div');
                    translatedDiv.innerHTML = cleanTranslatedText;
                    const translatedHasHTML = translatedDiv.children.length > 0 || cleanTranslatedText !== translatedDiv.textContent;
                    
                    if (translatedHasHTML) {
                      // Extract just the text content from translated HTML
                      const translatedTextOnly = translatedDiv.textContent || '';
                      elem.textContent = translatedTextOnly;
                    } else {
                      elem.textContent = cleanTranslatedText;
                    }
                    replaced = true;
                  }
                }
              }
              
              if (replaced) {
                return tempDiv.innerHTML;
              } else {
                // Fallback to replaceTextInHTML but strip HTML from translated text
                const translatedDiv = document.createElement('div');
                translatedDiv.innerHTML = cleanTranslatedText;
                const translatedTextOnly = translatedDiv.textContent || cleanTranslatedText;
                return replaceTextInHTML(content, originalText, translatedTextOnly);
              }
            }
            
            return content;
          }
          return content;
        });
      }

      // Recursively update innerBlocks
      if (Array.isArray(updatedBlock.innerBlocks) && updatedBlock.innerBlocks.length > 0) {
        updatedBlock.innerBlocks = updateBlockWithTranslation(
          updatedBlock.innerBlocks,
          originalText,
          translatedText,
          originalHtml
        );
      }

      return updatedBlock;
    });
  };


  if (!translationItems.length) {
    return (
      <div className="cp-wpml-translation-table-wrapper">
        <table id="cp-wpml-auto-translate-translation-table" className="cp-wpml-translation-table">
          <tbody>
            <tr>
              <td colSpan="3" className="cp-wpml-loading">
                Loading content...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    );
  }

  return (
    <div className="cp-wpml-translation-table-wrapper">
      <table id="cp-wpml-auto-translate-translation-table" className="cp-wpml-translation-table">
        <thead>
          <tr>
            <th>S.No</th>
            <th>Source Text</th>
            <th>Translation</th>
          </tr>
        </thead>
        <tbody id="cp-wpml-auto-translate-translation-tbody">
          {translationItems.map((item, index) => (
            <TranslationRow
              key={item.key}
              item={item}
              index={index + 1}
              translation={translationsMap[item.key] || ''}
              onTranslationChange={handleTranslationChange}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default TranslationTable;


