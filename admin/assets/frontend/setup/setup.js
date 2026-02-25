/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./modules/wizard/src/components/AiTranslation.jsx"
/*!*********************************************************!*\
  !*** ./modules/wizard/src/components/AiTranslation.jsx ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _SetupContinueButton__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./SetupContinueButton */ "./modules/wizard/src/components/SetupContinueButton.jsx");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../utils */ "./modules/wizard/src/utils.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const AiTranslation = ({
  onBack,
  onContinue
}) => {
  const data = window.wpml_at_setup || {};
  const dashboardUrl = data.dashboard_url || (data.admin_url || '').replace('admin.php', 'admin.php?page=automl_ai_dashboard');
  const saved_models = data.saved_models || {};
  const savedCreds = data.saved_credentials || {};
  const [openaiKey, setOpenaiKey] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(savedCreds.openai_key || '');
  const [googleKey, setGoogleKey] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(savedCreds.google_key || '');
  const [openaiModel, setOpenaiModel] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(saved_models.openai_model || '');
  const [googleModel, setGoogleModel] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(saved_models.google_model || '');
  const [saving, setSaving] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(false);
  const [message, setMessage] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(null);
  const [isError, setIsError] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(false);
  const handleSave = async () => {
    setSaving(true);
    setMessage(null);
    setIsError(false);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: 'automl-bulk-translate/wizard-save-credentials',
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': (0,_utils__WEBPACK_IMPORTED_MODULE_4__.getNonce)()
        },
        body: JSON.stringify({
          openai_key: openaiKey,
          google_key: googleKey,
          openai_model: openaiModel,
          google_model: googleModel
        })
      });
      setMessage((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('API keys saved.', 'automl-ai-translation-for-wpml'));
      setIsError(false);
      return true;
    } catch (err) {
      setMessage(err?.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to save. Please try again.', 'automl-ai-translation-for-wpml'));
      setIsError(true);
      return false;
    } finally {
      setSaving(false);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "wpml-at-wizard-card",
    style: {
      maxWidth: 600,
      margin: '0 auto',
      padding: 40,
      minHeight: '40vh'
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      style: {
        flex: 1
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h2", {
        style: {
          marginTop: 0
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('AI Translation', 'automl-ai-translation-for-wpml')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        style: {
          fontSize: 14,
          marginBottom: 12
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('AutoML lets you translate content using AI. Add your API keys below; they are saved to the same settings as AUTOML Ai Translate Settings.', 'automl-ai-translation-for-wpml')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        style: {
          marginBottom: 16
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("label", {
          htmlFor: "wpml-at-wizard-openai-key",
          style: {
            display: 'block',
            marginBottom: 6,
            fontWeight: 500
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('OpenAI API key', 'automl-ai-translation-for-wpml')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("input", {
          id: "wpml-at-wizard-openai-key",
          type: "password",
          value: openaiKey,
          onChange: e => setOpenaiKey(e.target.value),
          placeholder: "sk-...",
          style: {
            width: '100%',
            maxWidth: 400,
            padding: '8px 12px',
            fontSize: 14
          }
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        style: {
          marginBottom: 16
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("label", {
          htmlFor: "wpml-at-wizard-google-key",
          style: {
            display: 'block',
            marginBottom: 6,
            fontWeight: 500
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Google / Gemini API key', 'automl-ai-translation-for-wpml')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("input", {
          id: "wpml-at-wizard-google-key",
          type: "password",
          value: googleKey,
          onChange: e => setGoogleKey(e.target.value),
          placeholder: "...",
          style: {
            width: '100%',
            maxWidth: 400,
            padding: '8px 12px',
            fontSize: 14
          }
        })]
      }), message && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        style: {
          color: isError ? '#b32d2e' : '#00a32a',
          marginBottom: 12,
          fontSize: 14
        },
        children: message
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        style: {
          fontSize: 14,
          marginBottom: 24
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('You can also add or change keys and models later in AUTOML Ai Translate → Settings.', 'automl-ai-translation-for-wpml')
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      className: "wpml-at-wizard-footer",
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        marginTop: 24
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_3__.SetupBackButton, {
        onClick: onBack
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_3__["default"], {
        onClick: async () => {
          const saved = await handleSave();
          if (!saved) return;
          try {
            await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
              path: 'automl-bulk-translate/wizard-complete',
              method: 'POST',
              headers: {
                'X-WP-Nonce': (0,_utils__WEBPACK_IMPORTED_MODULE_4__.getNonce)()
              }
            });
          } catch (e) {}
          window.location.href = dashboardUrl;
        },
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Finish setup', 'automl-ai-translation-for-wpml'),
        disabled: saving
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AiTranslation);

/***/ },

/***/ "./modules/wizard/src/components/Languages.jsx"
/*!*****************************************************!*\
  !*** ./modules/wizard/src/components/Languages.jsx ***!
  \*****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../utils */ "./modules/wizard/src/utils.js");
/* harmony import */ var _SetupContinueButton__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./SetupContinueButton */ "./modules/wizard/src/components/SetupContinueButton.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const Languages = ({
  onBack,
  onContinue
}) => {
  const data = window.wpml_at_setup || {};
  const defaultCode = (data.default_language || '').toLowerCase();
  const allLanguages = Array.isArray(data.wpml_languages) ? data.wpml_languages : [];
  const wpmlLanguages = defaultCode ? allLanguages.filter(lang => (lang.code || '').toLowerCase() !== defaultCode) : allLanguages;
  const savedCode = data.saved_language && data.saved_language.code ? data.saved_language.code : '';
  const [selectedCode, setSelectedCode] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(savedCode);
  const handleContinue = async () => {
    if (selectedCode) {
      const selectedLang = wpmlLanguages.find(lang => (lang.code || '') === selectedCode);
      const payload = selectedLang ? {
        selected_language: {
          code: selectedLang.code,
          name: selectedLang.name || selectedLang.code,
          flag_url: selectedLang.flag_url,
          locale: selectedLang.locale
        }
      } : {
        selected_language: {
          code: selectedCode,
          name: '',
          flag_url: ''
        }
      };
      try {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
          path: 'automl-bulk-translate/wizard-save-language',
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (0,_utils__WEBPACK_IMPORTED_MODULE_3__.getNonce)()
          },
          body: JSON.stringify(payload)
        });
      } catch (err) {
        // Continue anyway
      }
    }
    onContinue();
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "wpml-at-wizard-card",
    style: {
      maxWidth: 600,
      margin: '0 auto',
      padding: 40,
      minHeight: '40vh'
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      style: {
        flex: 1,
        marginBottom: 20
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h2", {
        style: {
          marginTop: 0
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Translation Languages', 'automl-ai-translation-for-wpml')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        className: "wpml-at-wizard-intro",
        style: {
          marginBottom: 16
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Which languages do you want to translate your site into?', 'automl-ai-translation-for-wpml')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("label", {
        htmlFor: "wpml-at-wizard-language-select",
        style: {
          display: 'block',
          marginBottom: 8,
          fontWeight: 500
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose a language', 'automl-ai-translation-for-wpml')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        className: "wpml-at-wizard-intro",
        style: {
          marginBottom: 8,
          fontSize: 13,
          color: '#6b7280'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please select a language first. This is required to use AI translation and Settings.', 'automl-ai-translation-for-wpml')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("select", {
        id: "wpml-at-wizard-language-select",
        value: selectedCode,
        onChange: e => setSelectedCode(e.target.value),
        className: "wpml-at-wizard-select",
        style: {
          width: '100%',
          maxWidth: 400,
          padding: '8px 12px',
          fontSize: 14,
          border: '1px solid #8c8f94',
          borderRadius: 4,
          background: '#fff'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("option", {
          value: "",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select an option', 'automl-ai-translation-for-wpml')
        }), wpmlLanguages.map(lang => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("option", {
          value: lang.code,
          children: lang.name || lang.code
        }, lang.code))]
      }), wpmlLanguages.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        style: {
          marginTop: 12,
          color: '#b32d2e',
          fontSize: 14
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No languages found. Add languages in WPML → Languages first.', 'automl-ai-translation-for-wpml')
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      className: "wpml-at-wizard-footer",
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        marginTop: 24
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_4__.SetupBackButton, {
        onClick: onBack
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_SetupContinueButton__WEBPACK_IMPORTED_MODULE_4__["default"], {
        onClick: handleContinue,
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Continue', 'automl-ai-translation-for-wpml'),
        disabled: !selectedCode
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Languages);

/***/ },

/***/ "./modules/wizard/src/components/SetupContinueButton.jsx"
/*!***************************************************************!*\
  !*** ./modules/wizard/src/components/SetupContinueButton.jsx ***!
  \***************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SetupBackButton: () => (/* binding */ SetupBackButton),
/* harmony export */   "default": () => (/* binding */ SetupContinueButton)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


function SetupContinueButton({
  onClick,
  label,
  disabled
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("button", {
    type: "button",
    className: "button button-primary wpml-at-wizard-continue",
    onClick: onClick,
    disabled: disabled,
    style: {
      minWidth: 100
    },
    children: label || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Continue', 'automl-ai-translation-for-wpml')
  });
}
function SetupBackButton({
  onClick
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("button", {
    type: "button",
    className: "button",
    onClick: onClick,
    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Back', 'automl-ai-translation-for-wpml')
  });
}

/***/ },

/***/ "./modules/wizard/src/components/SetupProgress.jsx"
/*!*********************************************************!*\
  !*** ./modules/wizard/src/components/SetupProgress.jsx ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _VideoIntro__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./VideoIntro */ "./modules/wizard/src/components/VideoIntro.jsx");
/* harmony import */ var _Languages__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./Languages */ "./modules/wizard/src/components/Languages.jsx");
/* harmony import */ var _AiTranslation__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./AiTranslation */ "./modules/wizard/src/components/AiTranslation.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const STEPS = [{
  key: 'video_intro',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Intro', 'automl-ai-translation-for-wpml')
}, {
  key: 'languages',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Languages', 'automl-ai-translation-for-wpml')
}, {
  key: 'ai_translation',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('AI Translation', 'automl-ai-translation-for-wpml')
}];
const SetupProgress = ({
  currentStep,
  setCurrentStep,
  onGetStarted,
  onFinish
}) => {
  const currentIndex = STEPS.findIndex(s => s.key === currentStep);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "wpml-at-wizard-progress-wrap",
    style: {
      paddingBottom: 40
    },
    children: [currentStep !== 'video_intro' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "wpml-at-wizard-steps",
      children: STEPS.map((step, index) => {
        const isActive = step.key === currentStep;
        const isPast = index < currentIndex;
        const isInactive = !isActive && !isPast;
        const circleClass = isActive ? 'active' : isPast ? 'past' : 'inactive';
        const labelClass = isActive ? 'active' : isPast ? 'past' : 'inactive';
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)((react__WEBPACK_IMPORTED_MODULE_0___default().Fragment), {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
            className: "wpml-at-wizard-step-item",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
              className: `wpml-at-wizard-step-circle ${circleClass}`,
              children: isPast ? '✓' : index + 1
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
              className: `wpml-at-wizard-step-label ${labelClass}`,
              children: step.label
            })]
          }), index < STEPS.length - 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            className: "wpml-at-wizard-step-connector",
            "aria-hidden": "true"
          })]
        }, step.key);
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      children: [currentStep === 'video_intro' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_VideoIntro__WEBPACK_IMPORTED_MODULE_2__["default"], {
        onGetStarted: onGetStarted
      }), currentStep === 'languages' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_Languages__WEBPACK_IMPORTED_MODULE_3__["default"], {
        onBack: () => setCurrentStep('video_intro'),
        onContinue: () => setCurrentStep('ai_translation')
      }), currentStep === 'ai_translation' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_AiTranslation__WEBPACK_IMPORTED_MODULE_4__["default"], {
        onBack: () => setCurrentStep('languages'),
        onContinue: onFinish
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SetupProgress);

/***/ },

/***/ "./modules/wizard/src/components/VideoIntro.jsx"
/*!******************************************************!*\
  !*** ./modules/wizard/src/components/VideoIntro.jsx ***!
  \******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);



const VideoIntro = ({
  onGetStarted
}) => {
  const data = window.wpml_at_setup || {};
  const videoUrl = data.video_url || 'https://www.youtube.com/embed/dst_bf7uiTc';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    className: "wpml-at-wizard-card",
    style: {
      maxWidth: 600,
      margin: '12px auto',
      padding: 40,
      minHeight: '38vh'
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      style: {
        textAlign: 'center',
        marginBottom: 24
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("h3", {
        className: "wpml-at-wizard-card h2",
        style: {
          fontSize: '1.5rem',
          marginBottom: 12
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Watch Setup Guide', 'automl-ai-translation-for-wpml')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
        style: {
          color: '#6b7280',
          marginBottom: 24
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Learn how to configure AutoML for AI translation with WPML.', 'automl-ai-translation-for-wpml')
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      style: {
        position: 'relative',
        width: '100%',
        paddingBottom: '56.25%',
        marginBottom: 24
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("iframe", {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('AutoML Setup Guide', 'automl-ai-translation-for-wpml'),
        style: {
          position: 'absolute',
          top: 0,
          left: 0,
          width: '100%',
          height: '100%',
          borderRadius: 8
        },
        src: videoUrl,
        frameBorder: "0",
        allow: "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share",
        allowFullScreen: true
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      style: {
        display: 'flex',
        justifyContent: 'center',
        paddingTop: 16
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("button", {
        type: "button",
        className: "button button-primary button-hero",
        onClick: onGetStarted,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Get Started', 'automl-ai-translation-for-wpml')
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VideoIntro);

/***/ },

/***/ "./modules/wizard/src/pages/setup-page.jsx"
/*!*************************************************!*\
  !*** ./modules/wizard/src/pages/setup-page.jsx ***!
  \*************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_SetupProgress__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../components/SetupProgress */ "./modules/wizard/src/components/SetupProgress.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




const STEP_KEYS = ['video_intro', 'languages', 'ai_translation'];
function getStepFromUrl() {
  if (typeof window === 'undefined') return 'video_intro';
  const params = new URLSearchParams(window.location.search);
  const step = params.get('step');
  return STEP_KEYS.includes(step) ? step : 'video_intro';
}
function setStepInUrl(step) {
  const url = new URL(window.location.href);
  url.searchParams.set('step', step);
  window.history.replaceState({}, '', url.toString());
}
const SetupPage = () => {
  const [currentStep, setCurrentStep] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(getStepFromUrl);
  const [showReady, setShowReady] = react__WEBPACK_IMPORTED_MODULE_0___default().useState(false);
  react__WEBPACK_IMPORTED_MODULE_0___default().useEffect(() => {
    setStepInUrl(currentStep);
  }, [currentStep]);
  const data = window.wpml_at_setup || {};
  const dashboardUrl = data.dashboard_url || (data.admin_url || '').replace('admin.php', 'admin.php?page=automl_ai_dashboard');
  const handleGetStarted = () => {
    setCurrentStep('languages');
  };
  const handleFinish = () => {
    setShowReady(true);
  };
  if (showReady) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: "wpml-at-wizard-wrap",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: "wpml-at-wizard-card",
        style: {
          maxWidth: 600,
          margin: '0 auto',
          padding: 40
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h2", {
          style: {
            marginTop: 0
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("You're ready to translate with AI", 'automl-ai-translation-for-wpml')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
          style: {
            color: '#6b7280',
            marginBottom: 24
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Use the AUTOML Ai Translate dashboard to translate your posts and strings with AI.", 'automl-ai-translation-for-wpml')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("a", {
          href: dashboardUrl,
          className: "button button-primary button-hero",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Open AUTOML Ai Translate', 'automl-ai-translation-for-wpml')
        })]
      })
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
    className: "wpml-at-wizard-wrap",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h1", {
      style: {
        textAlign: 'center',
        paddingTop: 30,
        marginBottom: 16
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('AutoML – AI Translation for WPML', 'automl-ai-translation-for-wpml')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_components_SetupProgress__WEBPACK_IMPORTED_MODULE_2__["default"], {
      currentStep: currentStep,
      setCurrentStep: setCurrentStep,
      onGetStarted: handleGetStarted,
      onFinish: handleFinish
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SetupPage);

/***/ },

/***/ "./modules/wizard/src/utils.js"
/*!*************************************!*\
  !*** ./modules/wizard/src/utils.js ***!
  \*************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getNonce: () => (/* binding */ getNonce)
/* harmony export */ });
/**
 * Get nonce from localized script data.
 */
const getNonce = () => {
  return window.wpml_at_setup?.nonce || '';
};

/***/ },

/***/ "./node_modules/react-dom/client.js"
/*!******************************************!*\
  !*** ./node_modules/react-dom/client.js ***!
  \******************************************/
(__unused_webpack_module, exports, __webpack_require__) {



var m = __webpack_require__(/*! react-dom */ "react-dom");
if (false) // removed by dead control flow
{} else {
  var i = m.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
  exports.createRoot = function(c, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.createRoot(c, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
  exports.hydrateRoot = function(c, h, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.hydrateRoot(c, h, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
}


/***/ },

/***/ "react"
/*!************************!*\
  !*** external "React" ***!
  \************************/
(module) {

module.exports = window["React"];

/***/ },

/***/ "react-dom"
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
(module) {

module.exports = window["ReactDOM"];

/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*************************************!*\
  !*** ./modules/wizard/src/setup.js ***!
  \*************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _pages_setup_page_jsx__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./pages/setup-page.jsx */ "./modules/wizard/src/pages/setup-page.jsx");
/* harmony import */ var react_dom_client__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-dom/client */ "./node_modules/react-dom/client.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);



function mount() {
  const el = document.getElementById('wpml-at-setup');
  if (el) {
    const root = (0,react_dom_client__WEBPACK_IMPORTED_MODULE_1__.createRoot)(el);
    root.render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_pages_setup_page_jsx__WEBPACK_IMPORTED_MODULE_0__["default"], {}));
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount);
} else {
  mount();
}
})();

/******/ })()
;
//# sourceMappingURL=setup.js.map