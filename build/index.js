/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/commands"
/*!**********************************!*\
  !*** external ["wp","commands"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["commands"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/dom-ready"
/*!**********************************!*\
  !*** external ["wp","domReady"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["domReady"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/primitives"
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["primitives"];

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/calendar.mjs"
/*!*************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/calendar.mjs ***!
  \*************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ calendar_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/calendar.tsx


var calendar_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm.5 16c0 .3-.2.5-.5.5H5c-.3 0-.5-.2-.5-.5V7h15v12zM9 10H7v2h2v-2zm0 4H7v2h2v-2zm4-4h-2v2h2v-2zm4 0h-2v2h2v-2zm-4 4h-2v2h2v-2zm4 0h-2v2h2v-2z" }) });

//# sourceMappingURL=calendar.mjs.map


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
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
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
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_commands__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/commands */ "@wordpress/commands");
/* harmony import */ var _wordpress_commands__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_commands__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/calendar.mjs");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Registers a command palette entry for the ICS Event Importer.
 *
 * Uses a no-UI React component mounted via domReady() + createRoot()
 * so the command is available on all admin screens. The component
 * uses the useCommand hook for registration and useState for modal state.
 *
 * @package
 * @since   0.3.0
 */








/* global DataTransfer */

/**
 * ICS Import Modal component.
 *
 * Renders a drag-and-drop file upload area with template block toggles
 * and submits the ICS file to the server for processing.
 *
 * @param {Object}   props
 * @param {Function} props.onClose Callback to close the modal.
 * @return {JSX.Element} The modal UI for ICS file import.
 */

function ICSImportModal({
  onClose
}) {
  const [file, setFile] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [publishImport, setPublishImport] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const [includeTemplate, setIncludeTemplate] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const [templateBefore, setTemplateBefore] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [isSubmitting, setIsSubmitting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const fileInputRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(null);

  /**
   * Handles file selection from both the input and drag-and-drop.
   *
   * @param {File} selectedFile The selected file.
   */
  const handleFile = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useCallback)(selectedFile => {
    if (selectedFile && (selectedFile.name.endsWith('.ics') || selectedFile.type === 'text/calendar')) {
      setFile(selectedFile);
      setNotice(null);
    } else {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Please select a valid .ics file.', 'gatherpress-export-import')
      });
    }
  }, []);

  /**
   * Handles the DropZone file drop event.
   *
   * @param {File[]} files Dropped files.
   */
  const handleDrop = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useCallback)(files => {
    if (files && files.length > 0) {
      handleFile(files[0]);
    }
  }, [handleFile]);

  /**
   * Handles the file input change event.
   *
   * @param {Event} event The input change event.
   */
  const handleInputChange = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useCallback)(event => {
    if (event.target.files && event.target.files.length > 0) {
      handleFile(event.target.files[0]);
    }
  }, [handleFile]);

  /**
   * Submits the ICS file to the server via a hidden form POST.
   *
   * We use a traditional form submission because the server-side handler
   * expects a multipart/form-data POST with a nonce and redirects
   * after processing. This matches the existing Tools page behaviour.
   */
  const handleSubmit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useCallback)(() => {
    if (!file) {
      return;
    }
    setIsSubmitting(true);

    // Build a FormData object and submit via a hidden form.
    const form = document.createElement('form');
    form.method = 'POST';
    form.enctype = 'multipart/form-data';
    form.action = window.gpeiIcsImporter?.toolsUrl || '/wp-admin/tools.php';
    form.style.display = 'none';

    // Nonce field.
    const nonceInput = document.createElement('input');
    nonceInput.type = 'hidden';
    nonceInput.name = 'gpei_ics_nonce';
    nonceInput.value = window.gpeiIcsImporter?.nonce || '';
    form.appendChild(nonceInput);

    // Submit flag.
    const submitInput = document.createElement('input');
    submitInput.type = 'hidden';
    submitInput.name = 'gpei_ics_submit';
    submitInput.value = '1';
    form.appendChild(submitInput);

    // Publish flag.
    if (publishImport) {
      const publishInput = document.createElement('input');
      publishInput.type = 'hidden';
      publishInput.name = 'gpei_ics_publish';
      publishInput.value = '1';
      form.appendChild(publishInput);
    }

    // Include template.
    if (includeTemplate) {
      const templateInput = document.createElement('input');
      templateInput.type = 'hidden';
      templateInput.name = 'gpei_ics_include_template';
      templateInput.value = '1';
      form.appendChild(templateInput);
    }

    // Template before.
    if (includeTemplate && templateBefore) {
      const beforeInput = document.createElement('input');
      beforeInput.type = 'hidden';
      beforeInput.name = 'gpei_ics_template_before';
      beforeInput.value = '1';
      form.appendChild(beforeInput);
    }

    // File input — we need to use DataTransfer to set the file.
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.name = 'gpei_ics_file';
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    form.appendChild(fileInput);
    document.body.appendChild(form);
    form.submit();
  }, [file, publishImport, includeTemplate, templateBefore]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Modal, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Import Events from ICS File', 'gatherpress-export-import'),
    onRequestClose: onClose,
    size: "medium",
    children: [notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
      status: notice.status,
      onRemove: () => setNotice(null),
      isDismissible: true,
      children: notice.message
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
      style: {
        color: '#757575',
        fontSize: '13px',
        marginTop: 0
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Upload an ICS calendar file to import events into GatherPress. All imported events will be created as drafts for review.', 'gatherpress-export-import')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      onClick: () => fileInputRef.current?.click(),
      onKeyDown: e => {
        if (e.key === 'Enter' || e.key === ' ') {
          fileInputRef.current?.click();
        }
      },
      role: "button",
      tabIndex: 0,
      style: {
        border: '2px dashed ' + (file ? '#2271b1' : '#c3c4c7'),
        borderRadius: '8px',
        padding: '32px 24px',
        textAlign: 'center',
        cursor: 'pointer',
        transition: 'border-color 0.2s ease, background-color 0.2s ease',
        background: file ? '#f0f6fc' : '#f6f7f7',
        margin: '16px 0',
        position: 'relative'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.DropZone, {
        onFilesDrop: handleDrop
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          marginBottom: '8px'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
          className: "dashicons dashicons-calendar-alt",
          style: {
            fontSize: '36px',
            width: '36px',
            height: '36px',
            color: '#8c8f94'
          }
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
        style: {
          margin: '0 0 4px',
          fontSize: '14px',
          fontWeight: 500,
          color: '#1d2327'
        },
        children: file ? file.name : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Drag & drop your .ics file here', 'gatherpress-export-import')
      }), !file && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
        style: {
          margin: 0,
          color: '#8c8f94',
          fontSize: '13px'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('or click to browse', 'gatherpress-export-import')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
        ref: fileInputRef,
        type: "file",
        accept: ".ics,text/calendar",
        onChange: handleInputChange,
        style: {
          display: 'none'
        }
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
      style: {
        marginTop: 0,
        fontSize: '12px',
        color: '#a7aaad'
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Accepted format: .ics (iCalendar). Exports from Google Calendar, Outlook, Apple Calendar, and Event Organiser are supported.', 'gatherpress-export-import')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("fieldset", {
      style: {
        margin: '16px 0',
        padding: '12px 16px',
        border: '1px solid #dcdcde',
        borderRadius: '4px',
        background: '#f6f7f7'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.CheckboxControl, {
        __nextHasNoMarginBottom: true,
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Publish events and venues immediately', 'gatherpress-export-import'),
        checked: publishImport,
        onChange: setPublishImport,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('When disabled, imported events and venues are created as drafts for review.', 'gatherpress-export-import')
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("fieldset", {
      style: {
        margin: '16px 0',
        padding: '12px 16px',
        border: '1px solid #dcdcde',
        borderRadius: '4px',
        background: '#f6f7f7'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.CheckboxControl, {
        __nextHasNoMarginBottom: true,
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Include registered template blocks for events and venues', 'gatherpress-export-import'),
        checked: includeTemplate,
        onChange: setIncludeTemplate,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Inserts the default block template registered for gatherpress_event and gatherpress_venue post types into the created posts.', 'gatherpress-export-import')
      }), includeTemplate && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          marginTop: '12px',
          marginLeft: '28px'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.CheckboxControl, {
          __nextHasNoMarginBottom: true,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Insert template blocks before imported content', 'gatherpress-export-import'),
          checked: templateBefore,
          onChange: setTemplateBefore,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('When enabled, template blocks appear before the imported description. When disabled, they appear after.', 'gatherpress-export-import')
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      style: {
        display: 'flex',
        justifyContent: 'flex-end',
        gap: '8px',
        marginTop: '16px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
        variant: "tertiary",
        onClick: onClose,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Cancel', 'gatherpress-export-import')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
        variant: "primary",
        onClick: handleSubmit,
        disabled: !file || isSubmitting,
        isBusy: isSubmitting,
        children: isSubmitting ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Importing…', 'gatherpress-export-import') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Import Events', 'gatherpress-export-import')
      })]
    })]
  });
}

/**
 * No-UI React component that registers the command via useCommand
 * and manages the modal open/close state.
 *
 * Mounted via domReady() + createRoot() so the useCommand hook runs
 * inside a proper React tree with access to WordPress data stores.
 *
 * @return {JSX.Element|null} The import modal when open, or null.
 */
function GpeiIcsCommand() {
  const [isOpen, setIsOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  (0,_wordpress_commands__WEBPACK_IMPORTED_MODULE_2__.useCommand)({
    name: 'gatherpress-export-import/import-ics',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Import events from ICS file', 'gatherpress-export-import'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
    callback: () => setIsOpen(true)
  });
  if (!isOpen) {
    return null;
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ICSImportModal, {
    onClose: () => setIsOpen(false)
  });
}
_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default()(() => {
  const container = document.createElement('div');
  container.id = 'gpei-ics-command-root';
  document.body.appendChild(container);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createRoot)(container).render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(GpeiIcsCommand, {}));
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map