const { parse } = wp.blocks;
const { select, subscribe } = wp.data;

class blockDataRetrieve {
    constructor() {
        this.blockLists = [];
        this.customBlockTranslateData = {};
        this.customBlocksData = [];
        this.loaderContainer = null;
        this.init();
    }

    init = () => {
        // Log available blocks for translation
        const availableBlocks = wpmlAtBlockUpdateObject.available_blocks || [];
        console.log('Available blocks for translation:', availableBlocks);
        console.log('Total available blocks:', availableBlocks.length);
        
        this.fetchCustomBlocks();

        // Create full-page overlay and append to <body>
        this.loaderContainer = document.createElement('div');
        this.loaderContainer.className = 'wpml-at-overlay';
        this.loaderContainer.setAttribute('role', 'status');
        this.loaderContainer.setAttribute('aria-live', 'polite');
        this.loaderContainer.innerHTML = this.getOverlayTemplate();
        document.body.appendChild(this.loaderContainer);
        document.body.classList.add('wpml-at-overlay-open');
    }

    getBlocks = (blocks) => {
        const innerBlocks = (block) => {
            const innerBlock = block.innerBlocks;
            if (innerBlock.length > 0) {
                innerBlock.forEach(innerBlock => {
                    this.customBlocksData.push(innerBlock);
                    innerBlocks(innerBlock);
                });
            }
        }

        const blockLists = blocks;

        blockLists.forEach(block => {
            innerBlocks(block);
        });

        this.customBlocksData = [...this.customBlocksData, ...blockLists];

        this.getBlockData();
    }

    fetchCustomBlocks = () => {
        /**
         * Prepare data to send in API request.
        */
        const apiSendData = {
            wpml_at_nonce: wpmlAtBlockUpdateObject.ajax_nonce,
            action: wpmlAtBlockUpdateObject.action_get_content
        };
        const apiUrl = wpmlAtBlockUpdateObject.ajax_url;

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams(apiSendData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.data && data.data.message === 'No custom blocks found.') {
                    this.loaderContainer && this.loaderContainer.remove();
                    return;
                }

                if (data.success && data.data && data.data.block_data) {
                    const customBlocks = parse(data.data.block_data);
                    this.getBlocks(customBlocks);
                    // Save new block translate data
                    this.saveBlockData();
                } else {
                    this.loaderContainer && this.loaderContainer.remove();
                }
            })
            .catch(error => {
                this.loaderContainer && this.loaderContainer.remove();
                console.error('Error fetching block rules:', error);
            });
    }

    saveBlockData = () => {
        if (Object.keys(this.customBlockTranslateData).length < 1) {
            console.log('No block translation data to save');
            this.loaderContainer && this.loaderContainer.remove();
            return;
        }

        console.log('Saving block translation data:', this.customBlockTranslateData);
        console.log('Block names in data:', Object.keys(this.customBlockTranslateData));

        /**
        * Prepare data to send in API request & update latest translate block data.
       */
        const apiSendData = {
            wpml_at_nonce: wpmlAtBlockUpdateObject.ajax_nonce,
            action: wpmlAtBlockUpdateObject.action_update_content,
            save_block_data: JSON.stringify(this.customBlockTranslateData)
        };

        const apiUrl = wpmlAtBlockUpdateObject.ajax_url;

        console.log('Sending AJAX request with data:', apiSendData);

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams(apiSendData)
        })
            .then(response => response.json())
            .then(data => {
                console.log('AJAX response:', data);
                this.setOverlayState('success');
                this.teardownOverlay();
                if (data.success && data.data && data.data.message) {
                    console.log(data.data.message);
                } else if (data.success === false) {
                    console.error('AJAX error:', data.data);
                }
            })
            .catch(error => {
                this.setOverlayState('error');
                this.teardownOverlay();
                console.error('Error updating block rules:', error);
            });
    }

    nestedAttrValue = (idsArr) => {
        const convertToArrays = (obj) => {
            // Check if obj is an object
            if (typeof obj !== 'object' || obj === null) {
                return obj;
            }

            // Process each key-value pair in the object
            for (let key in obj) {
                if (obj.hasOwnProperty(key)) {
                    // If the current value is an object and has the key 'wpml_at_array_key_replace'
                    if (typeof obj[key] === 'object' && obj[key] !== null && obj[key].hasOwnProperty('wpml_at_array_key_replace')) {
                        // Replace the value with 'true' directly in the array
                        obj[key] = Object.values(obj[key]);
                        obj[key] = convertToArrays(obj[key]);
                    } else {
                        // Recursively call convertToArrays for nested objects or arrays
                        obj[key] = convertToArrays(obj[key]);
                    }
                }
            }

            return obj;
        }

        const deepMerge = (target, source) => {
            for (const key in source) {
                if (source[key] instanceof Object && key in target) {
                    Object.assign(source[key], deepMerge(target[key], source[key]));
                }
            }
            Object.assign(target || {}, source);
            return target;
        };

        let currentElement = {};
        let tempObj = currentElement;
        let lastKey = idsArr[idsArr.length - 1];
        idsArr.slice(0, -1).forEach((key) => {
            tempObj[key] = tempObj[key] || {};
            tempObj = tempObj[key];
        });
        tempObj[lastKey] = true;

        const obj = convertToArrays(currentElement);
        deepMerge(this.customBlockTranslateData, obj);
    }

    filterAttr = (idsArray, value) => {
        if (null === value || undefined === value) {
            return;
        }

        if (Object.getPrototypeOf(value) === Array.prototype) {
            this.filterBlockArrayAttr(idsArray, value);
        } else if (Object.getPrototypeOf(value) === Object.prototype) {
            this.filterBlockObjectAttr(idsArray, value);
        } else if (typeof value === 'string' && /Make This Content Available for Translation/i.test(value)) {
            this.nestedAttrValue(idsArray, value);
        } else if (value instanceof wp.richText.RichTextData && /Make This Content Available for Translation/i.test(value.originalHTML)) {
            this.nestedAttrValue(idsArray, value.originalHTML);
        }
    }

    filterBlockArrayAttr = (idsArr, blockData) => {
        const newIdArr = new Array(...idsArr);
        newIdArr.push('wpml_at_array_key_replace');
        blockData.forEach((value, key) => {
            if ((typeof value === 'string' && /Make This Content Available for Translation/i.test(value)) || (![null, undefined].includes(value) && [Array.prototype, Object.prototype].includes(Object.getPrototypeOf(value)))) {
                this.filterAttr(newIdArr, value)
            };
        });
    }

    filterBlockObjectAttr = (idsArr, blockData) => {
        Object.keys(blockData).forEach(key => {
            const newIdArr = new Array(...idsArr);
            const value = blockData[key];
            if (value !== null && value !== undefined) {
                if ((typeof value === 'string' && /Make This Content Available for Translation/i.test(value)) || [Array.prototype, Object.prototype].includes(Object.getPrototypeOf(value))) {
                    newIdArr.push(key);
                    this.filterAttr(newIdArr, blockData[key]);
                };
            }
        })
    }

    filterBlockAttribute = (blockData) => {
        Object.keys(blockData).map(clientId => {
            const blockName = Object.keys(blockData[clientId])[0];
            const attributes = blockData[clientId][blockName];
            Object.keys(attributes).forEach(keytwo => {
                const value = attributes[keytwo];
                const idsArray = new Array(blockName, "attributes", keytwo);
                this.filterAttr(idsArray, value);
            });

        })
    }

    getBlockData = () => {
        if (typeof this.customBlocksData !== 'object' || Object.keys(this.customBlocksData).length === 0) {
            return;
        }

        const blockData = this.customBlocksData;
        const blockAttributes = {};
        Object.values(blockData).forEach(block => {
            if (Object.values(block.attributes).length > 0) {
                blockAttributes[block.clientId] = {};
                blockAttributes[block.clientId][block.name] = block.attributes;
                console.log('Processing block:', block.name, 'with attributes:', Object.keys(block.attributes));
            }
        });

        console.log('Total blocks to process:', Object.keys(blockAttributes).length);
        console.log('Block names:', Object.values(blockAttributes).map(b => Object.keys(b)[0]));

        if (Object.values(blockAttributes).length > 0) {
            this.filterBlockAttribute(blockAttributes);
        }
    }

    setOverlayState = (state /* 'loading' | 'success' | 'error' */) => {
        if (!this.loaderContainer) return;
        const panel = this.loaderContainer.querySelector('.wpml-at-overlay .wpml-at-box');
        if (panel) panel.setAttribute('data-state', state);
    };
    
    teardownOverlay = (delayMs = 3000) => {
        if (!this.loaderContainer) return;
        setTimeout(() => {
            this.loaderContainer.classList.add('wpml-at-overlay--closing');
            setTimeout(() => {
                this.loaderContainer.remove();
                this.loaderContainer = null;
                document.body.classList.remove('wpml-at-overlay-open');
            }, 300);
        }, delayMs);
    };

    getOverlayTemplate = () => {
        return `
    <div class="wpml-at-overlay" role="status" aria-live="polite">
    <div class="wpml-at-backdrop"></div>
    <div class="wpml-at-box" data-state="loading">
      <div class="wpml-at-row">
        <span class="wpml-at-spinner" aria-hidden="true"></span>
        <span class="wpml-at-icon wpml-at-icon--ok" aria-hidden="true">✓</span>
        <span class="wpml-at-icon wpml-at-icon--err" aria-hidden="true">!</span>

        <div class="wpml-at-text">
          <div class="wpml-at-title" data-label="loading">Saving block content</div>
          <div class="wpml-at-title" data-label="success">Supported block content has been updated</div>
          <div class="wpml-at-title" data-label="error">Update failed</div>

          <div class="wpml-at-desc" data-label="loading">
            Please don't close or refresh this window until the update is complete.
          </div>
          <div class="wpml-at-desc" data-label="success">
            Supported block content has been updated. You may continue.
          </div>
          <div class="wpml-at-desc" data-label="error">
            Something went wrong. You can retry without closing this window.
          </div>
        </div>
      </div>

      <div class="wpml-at-bar"><span></span></div>
    </div>
  </div>
    `;
    }

}

const debounce = (func, delay) => {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
};

let isBlockContentUpdating = false;
const saveBlockContent = debounce(() => {
    new blockDataRetrieve();
    isBlockContentUpdating = false;
}, 300);

if (select && select('core/editor') && subscribe) {
    subscribe(() => {
        const {
            isCurrentPostPublished,
            isSavingPost,
            isPublishingPost,
            isAutosavingPost,
        } = select('core/editor');

        const isAutoSaving = isAutosavingPost();
        const isPublishing = isPublishingPost();
        const isSaving = isSavingPost();
        const postPublished = isCurrentPostPublished();

        if ((isPublishing || (postPublished && isSaving)) && !isAutoSaving && !isBlockContentUpdating) {
            isBlockContentUpdating = true;
            saveBlockContent();
        }

    })
}

