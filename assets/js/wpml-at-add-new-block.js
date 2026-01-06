'use strict';

(() => {
    const { createBlock } = wp.blocks;
    const { dispatch, select } = wp.data;
    
    class WpmlAtCreateNewBlock {
        constructor() {
            this.updateBlockStore = {};
            this.loaderRemove = null;
            this.loader = null;
            this.replaceAttributes = null;
            this.updateBlockId = [];
            this.blockProcessed = false; // Flag to prevent re-processing
        }

        copyTranslateText = () => {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(document.getElementById('wpml-at-copy-text'));
            selection.removeAllRanges();
            selection.addRange(range);
            document.execCommand('copy');
            selection.removeAllRanges();
        }

        noticeInitialize = () => {
            dispatch("core/notices").createNotice('info', 'To enable translation, please include the Make This Content Available for Translation text in your block content. For help, watch the video and click <b>"Copy Text"</b> to use. Then, paste it into the section of your block you want automatically translated', 
                {
                    isDismissible: false,
                    id: 'wpml-at-notice-id',
                    actions: [
                        {
                            label: 'Watch Video.',
                            url: `${wpmlAtAddBlockVars.demo_page_url}#custom-block-translate`,
                        },
                    ],
                    __unstableHTML: true
                }).then(() => {
                    const targetAnchor = document.querySelector(`a[href^="${wpmlAtAddBlockVars.demo_page_url}#custom-block-translate"]`);
                    if (targetAnchor) {
                        targetAnchor.addEventListener('click', (e) => {
                            e.preventDefault();
                            window.open(targetAnchor.href, '_blank');
                        });
                    }
                });
        }

        copyBtnInitialize = () => {
            const copyBtn = document.createElement('div');
            copyBtn.id = 'wpml-at-copy-btn';
            copyBtn.innerHTML = 'Copy Text';
            copyBtn.addEventListener('click', this.copyTranslateText);
            copyBtn.ariaLabel = 'Copy Text';
            copyBtn.title = 'Click to copy the text "Make This Content Available for Translation"';

            const copyText = document.createElement('div');
            copyText.id = 'wpml-at-copy-text';
            copyText.innerHTML = 'Make This Content Available for Translation';

            document.body.appendChild(copyBtn);
            document.body.appendChild(copyText);
        }

        addBlockInitialize = (newBlock) => {
            this.newBlock = newBlock;
            this.blockProcessed = false; // Reset flag for new block
            this.createNewBlock();
            this.skeletonLoader();
        }

        removeLoader = () => {
            clearTimeout(this.loaderRemove);
            this.loaderRemove = setTimeout(() => {
                if (this.loader) {
                    this.loader.remove();
                }
            }, 2000);
        }

        createNewBlock = () => {
            try {
                const newBlock = createBlock(this.newBlock);
                if (newBlock && newBlock.clientId) {
                    this.updateBlockData(newBlock);
                } else {
                    console.error('Failed to create block:', this.newBlock, newBlock);
                }
            } catch (error) {
                console.error('Error creating block:', this.newBlock, error);
            }
        }

        updateBlockData = async (Block) => {
            await dispatch('core/block-editor').insertBlocks([Block]);

            setTimeout(() => {
                const blockWrp = document.getElementById(`block-${Block.clientId}`);
                if (blockWrp) {
                    blockWrp.appendChild(this.loader);
                }
            }, 100);

            setTimeout(() => {
                this.updateBlockContent(Block);
            }, 400);
        }

        updateBlockContent = (Block) => {
            const newBlock = document.getElementById(`block-${Block.clientId}`);
            if (newBlock) {
                this.updateContent(newBlock);
            }
            return;
        }

        updateContent = async (ele) => {
            // Prevent re-processing if already done
            if (this.blockProcessed) {
                return;
            }

            const element = ele;
            let i = 1;
            this.removeLoader();

            if (element) {
                // Check if block has editable content areas
                const editableElements = element.querySelectorAll('[contenteditable="true"]');
                
                if (editableElements.length > 0) {
                    // Process editable elements
                    editableElements.forEach((editableEl, idx) => {
                        if (idx === 0 && editableEl.textContent.trim() === '') {
                            editableEl.textContent = 'Make This Content Available for Translation';
                        }
                    });
                    
                    // Process attributes
                    setTimeout(() => {
                        this.updateBlockFromStore();
                    }, 500);
                } else if (element.contentEditable == 'true' && element.children.length < 2) {
                    element.innerHTML = 'Make This Content Available for Translation';
                    this.blockProcessed = true;
                } else {
                    const innerElements = element.getElementsByTagName('*');
                    let hasProcessed = false;

                    for (let innerElement of innerElements) {
                        if (i === innerElements.length && !hasProcessed) {
                            this.removeLoader();
                            setTimeout(() => {
                                this.updateBlockFromStore();
                            }, 500);
                            hasProcessed = true;
                        }

                        i++;
                        if (["SCRIPT", "STYLE", "META", "LINK", "TITLE", "NOSCRIPT"].includes(innerElement.tagName)) {
                            continue;
                        }

                        if (innerElement.childNodes.length > 0) {
                            innerElement.childNodes.forEach((child) => {
                                if (child.nodeType === Node.TEXT_NODE && child.textContent.trim() !== '') {
                                    this.updateBlockAttr(innerElement, child);
                                }
                            });
                        }
                    }
                }
            }
        }

        updateBlockAttr = (innerElement, child) => {
            let blockId = false;
            if (innerElement.classList.contains('wp-block')) {
                blockId = innerElement.dataset.block;
            } else {
                const parentBlock = innerElement.closest('.wp-block');
                if (parentBlock) {
                    blockId = parentBlock.dataset.block;
                }
            }

            const blockAttributes = select('core/block-editor').getBlockAttributes(blockId);

            let index = 0;

            if (!this.updateBlockStore[blockId]) {
                let attributes = JSON.parse(JSON.stringify(blockAttributes));
                this.updateBlockStore[blockId] = { attributes: attributes };
                this.updateBlockStore[blockId].updateBlockData = {};
            }

            const updateNestedAttributes = async (attributes, child) => {
                const updateAttributes = async (key) => {
                    index++;

                    if (typeof attributes[key] === 'string' && attributes[key].trim() !== '' && (attributes[key].trim() === child.textContent.trim() || attributes[key] === child.textContent.trim())) {
                        const originalValue = attributes[key];
                        const newValue = 'Make This Content Available for Translation ' + index;
                        this.updateBlockStore[blockId].updateBlockData[newValue.replace(/\s+/g, '-')] = originalValue;
                        attributes[key] = newValue;
                    }
                };

                if (typeof attributes === 'object' && attributes !== null) {
                    for (const key of Object.keys(attributes)) {
                        await updateAttributes(key);

                        if (typeof attributes[key] === 'object' && attributes[key] !== null) {
                            await updateNestedAttributes(attributes[key], child);
                        }
                    }
                }
            };

            const blockStoreAttributes = this.updateBlockStore[blockId].attributes;
            updateNestedAttributes(blockStoreAttributes, child);
        }

        updateBlockFromStore = () => {
            const blockStoreAttributes = this.updateBlockStore;

            Object.keys(blockStoreAttributes).forEach((blockId) => {
                const blockAttributes = blockStoreAttributes[blockId].attributes;
                this.removeLoader();
                dispatch('core/block-editor').updateBlockAttributes(blockId, blockAttributes).then(() => {
                    clearTimeout(this.replaceAttributes);
                    this.replaceAttributes = setTimeout(() => {
                        this.removeLoader();
                        const blockIds = Object.keys(this.updateBlockStore);
                        this.replaceBlockContent(blockIds[0]);
                    }, 500);
                });
            });
        }

        replaceBlockContent = (blockId) => {
            const blockStoreAttributes = this.updateBlockStore;

            const checkValidAttributes = (value = false, blockId) => {
                const blockElement = document.querySelector(`#block-${blockId}`);
                const regex = new RegExp(value, 'g');
                const matchFound = regex.test(blockElement.innerText);
                return matchFound;
            };

            const blockAttributes = blockStoreAttributes[blockId].attributes;

            const updateNestedAttributes = async (attributes) => {
                const updateAttributes = async (key) => {
                    if (typeof attributes[key] === 'string' && attributes[key].includes('Make This Content Available for Translation')) {
                        try {
                            const keyWithDashes = attributes[key].replace(/\s+/g, '-');
                            const originalValue = this.updateBlockStore[blockId].updateBlockData[keyWithDashes];

                            const status = checkValidAttributes(attributes[key], blockId);

                            if (!status) {
                                attributes[key] = originalValue;
                            } else {
                                attributes[key] = 'Make This Content Available for Translation';
                            }
                        } catch (e) {
                            console.log(`${attributes[key]} is not valid.`);
                        }
                    }
                };

                if (typeof attributes === 'object' && attributes !== null) {
                    for (const key of Object.keys(attributes)) {
                        await updateAttributes(key);

                        if (typeof attributes[key] === 'object' && attributes[key] !== null) {
                            await updateNestedAttributes(attributes[key]);
                        }
                    }
                }
            };

            updateNestedAttributes(blockAttributes);

            setTimeout(() => {
                dispatch('core/block-editor').updateBlockAttributes(blockId, blockAttributes).then(() => {
                    const blockIds = Object.keys(this.updateBlockStore);
                    if (blockIds.length > 0) {
                        this.updateBlockId.push(blockId);
                        dispatch('core/block-editor').selectBlock(null);
                        this.removeLoader();
                        const firstBlockId = blockIds.find(id => !this.updateBlockId.includes(id));
                        if (firstBlockId) {
                            this.replaceBlockContent(firstBlockId);
                        }
                    }
                });
            }, 500);
        }

        skeletonLoader = () => {
            const loader = document.createElement('div');

            const loaderContainer = () => {
                const container = '<style>.wpml-at-loader-wrapper{position:absolute;width:100%;height:100%;top:0;left:0;z-index:99999;}.wpml-at-loader-container{width:100%;height:100%;}.wpml-at-loader-skeleton{--skbg:hsl(227deg, 13%, 50%, 0.2);display:grid;gap:20px;width:100%;height:100%;background:#ffffff;padding:15px;border-radius:8px;box-shadow:0 4px 12px rgba(0, 0, 0, 0.1);transition:transform 0.3s ease;transform:scale(1.02);}.wpml-at-loader-shimmer{display:flex;aspect-ratio:2/1;width:100%;height:100%;background:var(--skbg);border-radius:4px;overflow:hidden;position:relative;}.wpml-at-loader-shimmer::before{content:"";position:absolute;width:100%;height:100%;background-image:linear-gradient(-90deg,transparent 8%,rgba(255,255,255,0.28) 18%,transparent 33%);background-size:200%;animation:shimerAnimate 1.5s ease-in-out infinite;}@keyframes shimerAnimate{0%{background-position:100% 0;}100%{background-position:-100% 0;}}</style>';

                return '<div class="wpml-at-loader-container">' + container + '<div class="wpml-at-loader-skeleton"><span class="wpml-at-loader-shimmer"></span></div></div>';
            };

            loader.className = 'wpml-at-loader-wrapper';
            loader.innerHTML = loaderContainer();

            this.loader = loader;
        }
    }

    window.addEventListener('load', () => {
        const wpmlAtCreateBlockObj = new WpmlAtCreateNewBlock();

        wpmlAtCreateBlockObj.copyBtnInitialize();
        wpmlAtCreateBlockObj.noticeInitialize();

        const urlParams = new URLSearchParams(window.location.search);
        let newBlock = '';

        if (urlParams.has('wpml_at_new_block') && '' !== urlParams.get('wpml_at_new_block').trim()) {
            newBlock = urlParams.get('wpml_at_new_block');
            wpmlAtCreateBlockObj.addBlockInitialize(newBlock);
        }
    });
})();

