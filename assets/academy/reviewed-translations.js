(function () {
  'use strict';
  const data = document.getElementById('ha-reviewed-client-text');
  if (!data || window.haReviewedText) return;
  let map;
  try { map = JSON.parse(data.textContent); } catch (_) { return; }
  const has = text => Object.prototype.hasOwnProperty.call(map, text);
  const translate = text => typeof text === 'string' && has(text) ? map[text] : text;
  window.haReviewedText = translate;
  ['alert', 'confirm', 'prompt'].forEach(name => {
    const original = window[name].bind(window);
    window[name] = (message, ...args) => original(translate(message), ...args);
  });
  const excluded = 'script,style,textarea,pre,code,[contenteditable],[translate="no"],.ha-signed-translation';
  const attrs = ['title', 'alt', 'placeholder', 'aria-label'];
  function inspect(node) {
    const element = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
    if (!element || element.closest(excluded)) return;
    if (node.nodeType === Node.TEXT_NODE) {
      const key = node.nodeValue.trim();
      if (has(key) && map[key] !== key) node.nodeValue = node.nodeValue.replace(key, map[key]);
    } else if (node.nodeType === Node.ELEMENT_NODE) {
      attrs.forEach(attr => {
        const value = node.getAttribute(attr);
        if (has(value) && map[value] !== value) node.setAttribute(attr, map[value]);
      });
      if (node.matches('input[type="submit"],input[type="button"]')) {
        const value = node.getAttribute('value');
        if (has(value) && map[value] !== value) node.setAttribute('value', map[value]);
      }
      node.childNodes.forEach(inspect);
    }
  }
  const observer = new MutationObserver(records => {
    observer.disconnect();
    records.forEach(record => {
      if (record.type === 'childList') record.addedNodes.forEach(inspect);
      else inspect(record.target);
    });
    observe();
  });
  function observe() {
    observer.observe(document.body, {childList:true, subtree:true, characterData:true, attributes:true, attributeFilter:attrs.concat('value')});
  }
  inspect(document.body);
  observe();
})();
