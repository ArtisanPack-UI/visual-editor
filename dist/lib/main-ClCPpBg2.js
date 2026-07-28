import * as W from "react";
import _o, { useState as Y, Children as gr, isValidElement as Fe, cloneElement as Le, forwardRef as Ot, createElement as Se, useMemo as jt, useRef as F, useLayoutEffect as Mt, useCallback as At, useSyncExternalStore as To, useEffect as V, useReducer as ua, createContext as kt, useContext as vt, memo as la } from "react";
import fa, { flushSync as Ro, createPortal as Qn } from "react-dom";
import { jsx as C, Fragment as St, jsxs as Et } from "react/jsx-runtime";
var sm = typeof globalThis < "u" ? globalThis : typeof window < "u" ? window : typeof global < "u" ? global : typeof self < "u" ? self : {};
function Do(t) {
  return t && t.__esModule && Object.prototype.hasOwnProperty.call(t, "default") ? t.default : t;
}
function am(t) {
  if (t.__esModule) return t;
  var e = t.default;
  if (typeof e == "function") {
    var n = function r() {
      return this instanceof r ? Reflect.construct(e, arguments, this.constructor) : e.apply(this, arguments);
    };
    n.prototype = e.prototype;
  } else n = {};
  return Object.defineProperty(n, "__esModule", { value: !0 }), Object.keys(t).forEach(function(r) {
    var i = Object.getOwnPropertyDescriptor(t, r);
    Object.defineProperty(n, r, i.get ? i : {
      enumerable: !0,
      get: function() {
        return t[r];
      }
    });
  }), n;
}
var tr, en = fa;
if (process.env.NODE_ENV === "production")
  tr = en.createRoot, en.hydrateRoot;
else {
  var oi = en.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
  tr = function(t, e) {
    oi.usingClientEntryPoint = !0;
    try {
      return en.createRoot(t, e);
    } finally {
      oi.usingClientEntryPoint = !1;
    }
  };
}
function da(t) {
  return typeof t != "string" || t === "" ? (console.error("The namespace must be a non-empty string."), !1) : /^[a-zA-Z][a-zA-Z0-9_.\-\/]*$/.test(t) ? !0 : (console.error(
    "The namespace can only contain numbers, letters, dashes, periods, underscores and slashes."
  ), !1);
}
var Io = da;
function pa(t) {
  return typeof t != "string" || t === "" ? (console.error("The hook name must be a non-empty string."), !1) : /^__/.test(t) ? (console.error("The hook name cannot begin with `__`."), !1) : /^[a-zA-Z][a-zA-Z0-9_.-]*$/.test(t) ? !0 : (console.error(
    "The hook name can only contain numbers, letters, dashes, periods and underscores."
  ), !1);
}
var yr = pa;
function ma(t, e) {
  return function(r, i, o, s = 10) {
    const a = t[e];
    if (!yr(r) || !Io(i))
      return;
    if (typeof o != "function") {
      console.error("The hook callback must be a function.");
      return;
    }
    if (typeof s != "number") {
      console.error(
        "If specified, the hook priority must be a number."
      );
      return;
    }
    const u = { callback: o, priority: s, namespace: i };
    if (a[r]) {
      const c = a[r].handlers;
      let l;
      for (l = c.length; l > 0 && !(s >= c[l - 1].priority); l--)
        ;
      l === c.length ? c[l] = u : c.splice(l, 0, u), a.__current.forEach((f) => {
        f.name === r && f.currentIndex >= l && f.currentIndex++;
      });
    } else
      a[r] = {
        handlers: [u],
        runs: 0
      };
    r !== "hookAdded" && t.doAction(
      "hookAdded",
      r,
      i,
      o,
      s
    );
  };
}
var si = ma;
function ha(t, e, n = !1) {
  return function(i, o) {
    const s = t[e];
    if (!yr(i) || !n && !Io(o))
      return;
    if (!s[i])
      return 0;
    let a = 0;
    if (n)
      a = s[i].handlers.length, s[i] = {
        runs: s[i].runs,
        handlers: []
      };
    else {
      const u = s[i].handlers;
      for (let c = u.length - 1; c >= 0; c--)
        u[c].namespace === o && (u.splice(c, 1), a++, s.__current.forEach((l) => {
          l.name === i && l.currentIndex >= c && l.currentIndex--;
        }));
    }
    return i !== "hookRemoved" && t.doAction("hookRemoved", i, o), a;
  };
}
var nn = ha;
function va(t, e) {
  return function(r, i) {
    const o = t[e];
    return typeof i < "u" ? r in o && o[r].handlers.some(
      (s) => s.namespace === i
    ) : r in o;
  };
}
var ai = va;
function ga(t, e, n, r) {
  return function(o, ...s) {
    const a = t[e];
    a[o] || (a[o] = {
      handlers: [],
      runs: 0
    }), a[o].runs++;
    const u = a[o].handlers;
    if (process.env.NODE_ENV !== "production" && o !== "hookAdded" && a.all && u.push(...a.all.handlers), !u || !u.length)
      return n ? s[0] : void 0;
    const c = {
      name: o,
      currentIndex: 0
    };
    async function l() {
      try {
        a.__current.add(c);
        let m = n ? s[0] : void 0;
        for (; c.currentIndex < u.length; )
          m = await u[c.currentIndex].callback.apply(null, s), n && (s[0] = m), c.currentIndex++;
        return n ? m : void 0;
      } finally {
        a.__current.delete(c);
      }
    }
    function f() {
      try {
        a.__current.add(c);
        let m = n ? s[0] : void 0;
        for (; c.currentIndex < u.length; )
          m = u[c.currentIndex].callback.apply(null, s), n && (s[0] = m), c.currentIndex++;
        return n ? m : void 0;
      } finally {
        a.__current.delete(c);
      }
    }
    return (r ? l : f)();
  };
}
var rn = ga;
function ya(t, e) {
  return function() {
    const r = t[e];
    return Array.from(r.__current).at(-1)?.name ?? null;
  };
}
var ci = ya;
function ba(t, e) {
  return function(r) {
    const i = t[e];
    return typeof r > "u" ? i.__current.size > 0 : Array.from(i.__current).some(
      (o) => o.name === r
    );
  };
}
var ui = ba;
function wa(t, e) {
  return function(r) {
    const i = t[e];
    if (yr(r))
      return i[r] && i[r].runs ? i[r].runs : 0;
  };
}
var li = wa, xa = class {
  actions;
  filters;
  addAction;
  addFilter;
  removeAction;
  removeFilter;
  hasAction;
  hasFilter;
  removeAllActions;
  removeAllFilters;
  doAction;
  doActionAsync;
  applyFilters;
  applyFiltersAsync;
  currentAction;
  currentFilter;
  doingAction;
  doingFilter;
  didAction;
  didFilter;
  constructor() {
    this.actions = /* @__PURE__ */ Object.create(null), this.actions.__current = /* @__PURE__ */ new Set(), this.filters = /* @__PURE__ */ Object.create(null), this.filters.__current = /* @__PURE__ */ new Set(), this.addAction = si(this, "actions"), this.addFilter = si(this, "filters"), this.removeAction = nn(this, "actions"), this.removeFilter = nn(this, "filters"), this.hasAction = ai(this, "actions"), this.hasFilter = ai(this, "filters"), this.removeAllActions = nn(this, "actions", !0), this.removeAllFilters = nn(this, "filters", !0), this.doAction = rn(this, "actions", !1, !1), this.doActionAsync = rn(this, "actions", !1, !0), this.applyFilters = rn(this, "filters", !0, !1), this.applyFiltersAsync = rn(this, "filters", !0, !0), this.currentAction = ci(this, "actions"), this.currentFilter = ci(this, "filters"), this.doingAction = ui(this, "actions"), this.doingFilter = ui(this, "filters"), this.didAction = li(this, "actions"), this.didFilter = li(this, "filters");
  }
};
function Sa() {
  return new xa();
}
var Ea = Sa, No = Ea(), {
  addAction: cm,
  addFilter: er,
  removeAction: um,
  removeFilter: lm,
  hasAction: fm,
  hasFilter: dm,
  removeAllActions: pm,
  removeAllFilters: mm,
  doAction: Aa,
  doActionAsync: hm,
  applyFilters: nr,
  applyFiltersAsync: vm,
  currentAction: gm,
  currentFilter: ym,
  doingAction: bm,
  doingFilter: wm,
  didAction: xm,
  didFilter: Sm,
  actions: Em,
  filters: Am
} = No;
const fi = Number.MIN_SAFE_INTEGER, Pa = [
  ["ap.visual-editor.resources", "ap.visualEditor.resources"],
  ["ap.visual-editor.templates", "ap.visualEditor.templates"],
  ["ap.visual-editor.template-parts", "ap.visualEditor.templateParts"],
  ["ap.visual-editor.patterns", "ap.visualEditor.patterns"],
  ["ap.visual-editor.global-styles", "ap.visualEditor.globalStyles"],
  ["ap.visual-editor.navigation", "ap.visualEditor.navigation"],
  [
    "ap.visual-editor.visibility.register-rules",
    "ap.visualEditor.visibility.registerRules"
  ],
  [
    "ap.visual-editor.visibility.evaluated",
    "ap.visualEditor.visibility.evaluated"
  ],
  [
    "ap.visual-editor.visibility.user-search-results",
    "ap.visualEditor.visibility.userSearchResults"
  ],
  ["ap.visual-editor.rendered-block", "ap.visualEditor.renderedBlock"],
  ["ap.visual-editor.breadcrumbs.trail", "ap.visualEditor.breadcrumbs.trail"],
  [
    "ap.visual-editor.loginout.envelope",
    "ap.visualEditor.loginout.envelope"
  ],
  [
    "ap.visual-editor.loginout.login-form",
    "ap.visualEditor.loginout.loginForm"
  ],
  ["ap.icons.register-icon-sets", "ap.icons.registerIconSets"],
  // JS-only hooks (no PHP counterpart) — renamed for surface consistency.
  [
    "ap.visual-editor.background-controls",
    "ap.visualEditor.backgroundControls"
  ],
  ["ap.visual-editor.canvas-styles", "ap.visualEditor.canvasStyles"],
  ["ap.visual-editor.document-panels", "ap.visualEditor.documentPanels"]
], di = /* @__PURE__ */ new Set(), me = /* @__PURE__ */ new Map();
function pi(t) {
  me.set(t, (me.get(t) ?? 0) + 1);
}
function mi(t) {
  const e = me.get(t) ?? 0;
  e <= 1 ? me.delete(t) : me.set(t, e - 1);
}
function hi(t) {
  return (me.get(t) ?? 0) > 0;
}
function Ca() {
  for (const [t, e] of Pa) {
    const n = `${t}=>${e}`;
    di.has(n) || (di.add(n), er(
      t,
      "artisanpack-ui/visual-editor/hook-alias-forward",
      (r, ...i) => {
        if (hi(t))
          return r;
        pi(e);
        try {
          return nr(e, r, ...i);
        } finally {
          mi(e);
        }
      },
      fi
    ), er(
      e,
      "artisanpack-ui/visual-editor/hook-alias-reverse",
      (r, ...i) => {
        if (hi(e))
          return r;
        pi(t);
        try {
          return nr(t, r, ...i);
        } finally {
          mi(t);
        }
      },
      fi
    ));
  }
}
function Oa(t) {
  const e = {
    id: t.id,
    url: t.url,
    alt: t.alt_text ?? "",
    caption: t.caption ?? "",
    mime: t.mime_type
  }, n = Ra(t);
  n !== null && (e.media_type = n), typeof t.width == "number" && (e.width = t.width), typeof t.height == "number" && (e.height = t.height), typeof t.file_name == "string" && t.file_name.length > 0 && (e.filename = t.file_name);
  const r = Da(t);
  return r !== null && (e.sizes = r), e;
}
function _a(t) {
  if (!t || t.length === 0)
    return;
  const e = /* @__PURE__ */ new Set();
  for (const n of t) {
    const r = Ta(n);
    r !== null && e.add(r);
  }
  if (e.size !== 0)
    return Array.from(e);
}
function Ta(t) {
  const e = t.toLowerCase().trim();
  return e === "image" || e.startsWith("image/") ? "image" : e === "video" || e.startsWith("video/") ? "video" : e === "audio" || e.startsWith("audio/") ? "audio" : e === "document" || e === "application" || e.startsWith("application/") || e.startsWith("text/") ? "document" : null;
}
function Ra(t) {
  if (t.is_image)
    return "image";
  if (t.is_video)
    return "video";
  if (t.is_audio)
    return "audio";
  if (t.is_document)
    return "file";
  switch (t.mime_type.split("/")[0]?.toLowerCase()) {
    case "image":
      return "image";
    case "video":
      return "video";
    case "audio":
      return "audio";
    case "application":
    case "text":
      return "file";
    default:
      return null;
  }
}
function Da(t) {
  const e = t.metadata?.sizes;
  if (!e || typeof e != "object")
    return null;
  const n = {};
  for (const [r, i] of Object.entries(
    e
  )) {
    if (typeof i == "string") {
      n[r] = { url: i };
      continue;
    }
    if (i && typeof i == "object") {
      const o = i;
      if (typeof o.url != "string")
        continue;
      const s = { url: o.url };
      typeof o.width == "number" && (s.width = o.width), typeof o.height == "number" && (s.height = o.height), n[r] = s;
    }
  }
  return Object.keys(n).length === 0 ? null : n;
}
const Ia = "artisanpack-ui/visual-editor/media-bridge";
let Mo = null, Fo = null, vi = !1;
function Lo(t) {
  Mo = t.MediaBridge, Fo = t.uploadMedia, Na();
}
function gi() {
  return Mo;
}
function Pm() {
  return Fo;
}
function Na() {
  vi || (er(
    "editor.MediaUpload",
    Ia,
    () => ko
  ), vi = !0);
}
let ko = null;
function Ma(t) {
  ko = t;
}
function Fa(t) {
  const e = {
    MediaBridge: t.MediaModal,
    uploadMedia: t.uploadMedia
  };
  Lo(e);
}
var rr, Vo, De, $o;
rr = {
  "(": 9,
  "!": 8,
  "*": 7,
  "/": 7,
  "%": 7,
  "+": 6,
  "-": 6,
  "<": 5,
  "<=": 5,
  ">": 5,
  ">=": 5,
  "==": 4,
  "!=": 4,
  "&&": 3,
  "||": 2,
  "?": 1,
  "?:": 1
};
Vo = ["(", "?"];
De = {
  ")": ["("],
  ":": ["?", "?:"]
};
$o = /<=|>=|==|!=|&&|\|\||\?:|\(|!|\*|\/|%|\+|-|<|>|\?|\)|:/;
function La(t) {
  for (var e = [], n = [], r, i, o, s; r = t.match($o); ) {
    for (i = r[0], o = t.substr(0, r.index).trim(), o && e.push(o); s = n.pop(); ) {
      if (De[i]) {
        if (De[i][0] === s) {
          i = De[i][1] || i;
          break;
        }
      } else if (Vo.indexOf(s) >= 0 || rr[s] < rr[i]) {
        n.push(s);
        break;
      }
      e.push(s);
    }
    De[i] || n.push(i), t = t.substr(r.index + i.length);
  }
  return t = t.trim(), t && e.push(t), e.concat(n.reverse());
}
var ka = {
  "!": function(t) {
    return !t;
  },
  "*": function(t, e) {
    return t * e;
  },
  "/": function(t, e) {
    return t / e;
  },
  "%": function(t, e) {
    return t % e;
  },
  "+": function(t, e) {
    return t + e;
  },
  "-": function(t, e) {
    return t - e;
  },
  "<": function(t, e) {
    return t < e;
  },
  "<=": function(t, e) {
    return t <= e;
  },
  ">": function(t, e) {
    return t > e;
  },
  ">=": function(t, e) {
    return t >= e;
  },
  "==": function(t, e) {
    return t === e;
  },
  "!=": function(t, e) {
    return t !== e;
  },
  "&&": function(t, e) {
    return t && e;
  },
  "||": function(t, e) {
    return t || e;
  },
  "?:": function(t, e, n) {
    if (t)
      throw e;
    return n;
  }
};
function Va(t, e) {
  var n = [], r, i, o, s, a, u;
  for (r = 0; r < t.length; r++) {
    if (a = t[r], s = ka[a], s) {
      for (i = s.length, o = Array(i); i--; )
        o[i] = n.pop();
      try {
        u = s.apply(null, o);
      } catch (c) {
        return c;
      }
    } else e.hasOwnProperty(a) ? u = e[a] : u = +a;
    n.push(u);
  }
  return n[0];
}
function $a(t) {
  var e = La(t);
  return function(n) {
    return Va(e, n);
  };
}
function Ba(t) {
  var e = $a(t);
  return function(n) {
    return +e({ n });
  };
}
var yi = {
  contextDelimiter: "",
  onMissingKey: null
};
function Ha(t) {
  var e, n, r;
  for (e = t.split(";"), n = 0; n < e.length; n++)
    if (r = e[n].trim(), r.indexOf("plural=") === 0)
      return r.substr(7);
}
function br(t, e) {
  var n;
  this.data = t, this.pluralForms = {}, this.options = {};
  for (n in yi)
    this.options[n] = e !== void 0 && n in e ? e[n] : yi[n];
}
br.prototype.getPluralForm = function(t, e) {
  var n = this.pluralForms[t], r, i, o;
  return n || (r = this.data[t][""], o = r["Plural-Forms"] || r["plural-forms"] || // Ignore reason: As known, there's no way to document the empty
  // string property on a key to guarantee this as metadata.
  // @ts-ignore
  r.plural_forms, typeof o != "function" && (i = Ha(
    r["Plural-Forms"] || r["plural-forms"] || // Ignore reason: As known, there's no way to document the empty
    // string property on a key to guarantee this as metadata.
    // @ts-ignore
    r.plural_forms
  ), o = Ba(i)), n = this.pluralForms[t] = o), n(e);
};
br.prototype.dcnpgettext = function(t, e, n, r, i) {
  var o, s, a;
  return i === void 0 ? o = 0 : o = this.getPluralForm(t, i), s = n, e && (s = e + this.options.contextDelimiter + n), a = this.data[t][s], a && a[o] ? a[o] : (this.options.onMissingKey && this.options.onMissingKey(n, t), o === 0 ? n : r);
};
var bi = {
  "": {
    plural_forms(t) {
      return t === 1 ? 0 : 1;
    }
  }
}, Wa = /^i18n\.(n?gettext|has_translation)(_|$)/, ja = (t, e, n) => {
  const r = new br({}), i = /* @__PURE__ */ new Set(), o = () => {
    i.forEach((g) => g());
  }, s = (g) => (i.add(g), () => i.delete(g)), a = (g = "default") => r.data[g], u = (g, y = "default") => {
    r.data[y] = {
      ...r.data[y],
      ...g
    }, r.data[y][""] = {
      ...bi[""],
      ...r.data[y]?.[""]
    }, delete r.pluralForms[y];
  }, c = (g, y) => {
    u(g, y), o();
  }, l = (g, y = "default") => {
    r.data[y] = {
      ...r.data[y],
      ...g,
      // Populate default domain configuration (supported locale date which omits
      // a plural forms expression).
      "": {
        ...bi[""],
        ...r.data[y]?.[""],
        ...g?.[""]
      }
    }, delete r.pluralForms[y], o();
  }, f = (g, y) => {
    r.data = {}, r.pluralForms = {}, c(g, y);
  }, m = (g = "default", y, E, S, A) => (r.data[g] || u(void 0, g), r.dcnpgettext(g, y, E, S, A)), h = (g) => g || "default", p = (g, y) => {
    let E = m(y, void 0, g);
    return n ? (E = n.applyFilters(
      "i18n.gettext",
      E,
      g,
      y
    ), n.applyFilters(
      "i18n.gettext_" + h(y),
      E,
      g,
      y
    )) : E;
  }, d = (g, y, E) => {
    let S = m(E, y, g);
    return n ? (S = n.applyFilters(
      "i18n.gettext_with_context",
      S,
      g,
      y,
      E
    ), n.applyFilters(
      "i18n.gettext_with_context_" + h(E),
      S,
      g,
      y,
      E
    )) : S;
  }, w = (g, y, E, S) => {
    let A = m(
      S,
      void 0,
      g,
      y,
      E
    );
    return n ? (A = n.applyFilters(
      "i18n.ngettext",
      A,
      g,
      y,
      E,
      S
    ), n.applyFilters(
      "i18n.ngettext_" + h(S),
      A,
      g,
      y,
      E,
      S
    )) : A;
  }, x = (g, y, E, S, A) => {
    let _ = m(
      A,
      S,
      g,
      y,
      E
    );
    return n ? (_ = n.applyFilters(
      "i18n.ngettext_with_context",
      _,
      g,
      y,
      E,
      S,
      A
    ), n.applyFilters(
      "i18n.ngettext_with_context_" + h(A),
      _,
      g,
      y,
      E,
      S,
      A
    )) : _;
  }, v = () => d("ltr", "text direction") === "rtl", b = (g, y, E) => {
    const S = y ? y + "" + g : g;
    let A = !!r.data?.[E ?? "default"]?.[S];
    return n && (A = n.applyFilters(
      "i18n.has_translation",
      A,
      g,
      y,
      E
    ), A = n.applyFilters(
      "i18n.has_translation_" + h(E),
      A,
      g,
      y,
      E
    )), A;
  };
  if (n) {
    const g = (y) => {
      Wa.test(y) && o();
    };
    n.addAction("hookAdded", "core/i18n", g), n.addAction("hookRemoved", "core/i18n", g);
  }
  return {
    getLocaleData: a,
    setLocaleData: c,
    addLocaleData: l,
    resetLocaleData: f,
    subscribe: s,
    __: p,
    _x: d,
    _n: w,
    _nx: x,
    isRTL: v,
    hasTranslation: b
  };
}, J = ja(void 0, void 0, No);
J.getLocaleData.bind(J);
var za = J.setLocaleData.bind(J);
J.resetLocaleData.bind(J);
J.subscribe.bind(J);
var Ga = J.__.bind(J), Cm = J._x.bind(J), Om = J._n.bind(J);
J._nx.bind(J);
var _m = J.isRTL.bind(J);
J.hasTranslation.bind(J);
const wr = "artisanpack-visual-editor", Ua = {
  "": {
    domain: wr,
    lang: "en"
  }
};
let wi = !1;
function Tm() {
  wi || (za(Ua, wr), wi = !0);
}
const Xa = "artisanpack-ui/visual-editor/media-upload";
function Ya(t) {
  const [e, n] = Y(!1), r = gi(), { render: i, children: o } = t, s = Ka({
    render: i,
    children: o,
    open: () => {
      if (gi() === null) {
        qa();
        return;
      }
      n(!0);
    }
  });
  if (r === null || !e)
    return /* @__PURE__ */ C(St, { children: s });
  const a = t.multiple === !0 || t.multiple === "add" || t.gallery === !0, u = (c) => {
    if (typeof t.onSelect != "function")
      return;
    const l = c.map(Oa);
    if (a)
      t.onSelect(l);
    else {
      const f = l[0];
      f !== void 0 && t.onSelect(f);
    }
  };
  return /* @__PURE__ */ Et(St, { children: [
    s,
    /* @__PURE__ */ C(
      r,
      {
        open: e,
        onClose: () => n(!1),
        onSelect: (c) => {
          u(c), n(!1);
        },
        multiSelect: a,
        allowedTypes: _a(t.allowedTypes),
        context: Xa,
        title: t.title
      }
    )
  ] });
}
Ma(Ya);
function Ka(t) {
  const { render: e, children: n, open: r } = t;
  if (typeof e == "function")
    return e({ open: r });
  const i = gr.toArray(n).find(Fe);
  if (!i)
    return null;
  const o = i, s = o.props.onClick;
  return Le(o, {
    onClick: (a) => {
      s?.(a), !Za(a) && r();
    }
  });
}
function Za(t) {
  return typeof t == "object" && t !== null && "defaultPrevented" in t && t.defaultPrevented === !0;
}
function qa() {
  window.alert(
    Ga(
      "Media picker is not configured. Call registerArtisanpackMediaBridge() with MediaModal and uploadMedia from artisanpack-ui/media-library, or registerMediaBridge() with a custom picker.",
      wr
    )
  );
}
const Rm = "ve:editor:change", Dm = "ve:editor:autosave", Im = "ve:editor:save";
function Nm(t, e) {
  typeof window > "u" || window.dispatchEvent(new CustomEvent(t, { detail: e }));
}
function Bo(t) {
  var e, n, r = "";
  if (typeof t == "string" || typeof t == "number") r += t;
  else if (typeof t == "object") if (Array.isArray(t)) {
    var i = t.length;
    for (e = 0; e < i; e++) t[e] && (n = Bo(t[e])) && (r && (r += " "), r += n);
  } else for (n in t) t[n] && (r && (r += " "), r += n);
  return r;
}
function Pn() {
  for (var t, e, n = 0, r = "", i = arguments.length; n < i; n++) (t = arguments[n]) && (e = Bo(t)) && (r && (r += " "), r += e);
  return r;
}
var Ja = (t) => typeof t == "number" ? !1 : typeof t?.valueOf() == "string" || Array.isArray(t) ? !t.length : !t;
/*!
 * is-plain-object <https://github.com/jonschlinkert/is-plain-object>
 *
 * Copyright (c) 2014-2017, Jon Schlinkert.
 * Released under the MIT License.
 */
function xi(t) {
  return Object.prototype.toString.call(t) === "[object Object]";
}
function Qa(t) {
  var e, n;
  return xi(t) === !1 ? !1 : (e = t.constructor, e === void 0 ? !0 : (n = e.prototype, !(xi(n) === !1 || n.hasOwnProperty("isPrototypeOf") === !1)));
}
var hn = function() {
  return hn = Object.assign || function(e) {
    for (var n, r = 1, i = arguments.length; r < i; r++) {
      n = arguments[r];
      for (var o in n) Object.prototype.hasOwnProperty.call(n, o) && (e[o] = n[o]);
    }
    return e;
  }, hn.apply(this, arguments);
};
function tc(t) {
  return t.toLowerCase();
}
var ec = [/([a-z0-9])([A-Z])/g, /([A-Z])([A-Z][a-z])/g], nc = /[^A-Z0-9]+/gi;
function rc(t, e) {
  e === void 0 && (e = {});
  for (var n = e.splitRegexp, r = n === void 0 ? ec : n, i = e.stripRegexp, o = i === void 0 ? nc : i, s = e.transform, a = s === void 0 ? tc : s, u = e.delimiter, c = u === void 0 ? " " : u, l = Si(Si(t, r, "$1\0$2"), o, "\0"), f = 0, m = l.length; l.charAt(f) === "\0"; )
    f++;
  for (; l.charAt(m - 1) === "\0"; )
    m--;
  return l.slice(f, m).split("\0").map(a).join(c);
}
function Si(t, e, n) {
  return e instanceof RegExp ? t.replace(e, n) : e.reduce(function(r, i) {
    return r.replace(i, n);
  }, t);
}
function ic(t, e) {
  return e === void 0 && (e = {}), rc(t, hn({ delimiter: "." }, e));
}
function oc(t, e) {
  return e === void 0 && (e = {}), ic(t, hn({ delimiter: "-" }, e));
}
var Mm = (t) => Se("circle", t), Fm = (t) => Se("g", t), Ho = (t) => Se("path", t), Lm = (t) => Se("rect", t), ke = Ot(
  /**
   * @param {SVGProps}                          props isPressed indicates whether the SVG should appear as pressed.
   *                                                  Other props will be passed through to svg component.
   * @param {React.ForwardedRef<SVGSVGElement>} ref   The forwarded ref to the SVG element.
   *
   * @return {React.JSX.Element} Stop component
   */
  ({ className: t, isPressed: e, ...n }, r) => {
    const i = {
      ...n,
      className: Pn(t, { "is-pressed": e }) || void 0,
      "aria-hidden": !0,
      focusable: !1
    };
    return /* @__PURE__ */ C("svg", { ...i, ref: r });
  }
);
ke.displayName = "SVG";
function vn() {
  const t = /* @__PURE__ */ new Map(), e = /* @__PURE__ */ new Map();
  function n(r) {
    const i = e.get(r);
    if (i)
      for (const o of i)
        o();
  }
  return {
    get(r) {
      return t.get(r);
    },
    set(r, i) {
      t.set(r, i), n(r);
    },
    delete(r) {
      t.delete(r), n(r);
    },
    subscribe(r, i) {
      let o = e.get(r);
      return o || (o = /* @__PURE__ */ new Set(), e.set(r, o)), o.add(i), () => {
        o.delete(i), o.size === 0 && e.delete(r);
      };
    }
  };
}
function sc(t, e) {
  if (t === e)
    return !0;
  const n = Object.keys(t), r = Object.keys(e);
  if (n.length !== r.length)
    return !1;
  let i = 0;
  for (; i < n.length; ) {
    const o = n[i], s = t[o];
    if (
      // In iterating only the keys of the first object after verifying
      // equal lengths, account for the case that an explicit `undefined`
      // value in the first is implicitly undefined in the second.
      //
      // Example: isShallowEqualObjects( { a: undefined }, { b: 5 } )
      s === void 0 && !e.hasOwnProperty(o) || s !== e[o]
    )
      return !1;
    i++;
  }
  return !0;
}
function ac(t, e) {
  if (t === e)
    return !0;
  if (t.length !== e.length)
    return !1;
  for (let n = 0, r = t.length; n < r; n++)
    if (t[n] !== e[n])
      return !1;
  return !0;
}
function cc(t, e) {
  if (t && e) {
    if (t.constructor === Object && e.constructor === Object)
      return sc(t, e);
    if (Array.isArray(t) && Array.isArray(e))
      return ac(t, e);
  }
  return t === e;
}
var Ei = /* @__PURE__ */ Object.create(null);
function Wo(t, e = {}) {
  const { since: n, version: r, alternative: i, plugin: o, link: s, hint: a } = e, u = o ? ` from ${o}` : "", c = n ? ` since version ${n}` : "", l = r ? ` and will be removed${u} in version ${r}` : "", f = i ? ` Please use ${i} instead.` : "", m = s ? ` See: ${s}` : "", h = a ? ` Note: ${a}` : "", p = `${t} is deprecated${c}${l}.${f}${m}${h}`;
  p in Ei || (Aa("deprecated", t, e, p), console.warn(p), Ei[p] = !0);
}
var Ai = /* @__PURE__ */ new WeakMap();
function uc(t) {
  const e = Ai.get(t) || 0;
  return Ai.set(t, e + 1), e;
}
function lc(t, e, n) {
  return jt(() => {
    if (n)
      return n;
    const r = uc(t);
    return e ? `${e}-${r}` : r;
  }, [t, n, e]);
}
var jo = lc;
function on(t, e) {
  typeof t == "function" ? t(e) : t && t.hasOwnProperty("current") && (t.current = e);
}
function zo(t) {
  const e = F(null), n = F(!1), r = F(!1), i = F([]), o = F(t);
  return o.current = t, Mt(() => {
    r.current === !1 && n.current === !0 && t.forEach((s, a) => {
      const u = i.current[a];
      s !== u && (on(u, null), on(s, e.current));
    }), i.current = t;
  }, t), Mt(() => {
    r.current = !1;
  }), At((s) => {
    on(e, s), r.current = !0, n.current = s !== null;
    const a = s ? o.current : i.current;
    for (const u of a)
      on(u, s);
  }, []);
}
var Vn = /* @__PURE__ */ new WeakMap();
function fc(t, e) {
  if (!e)
    return null;
  const n = Vn.get(t) ?? /* @__PURE__ */ new Map();
  Vn.has(t) || Vn.set(t, n);
  let r = n.get(e);
  return r || (typeof t?.matchMedia == "function" ? (r = t.matchMedia(e), n.set(e, r), r) : null);
}
function dc(t, e = window) {
  const n = jt(() => {
    const r = fc(e, t);
    return {
      subscribe(i) {
        return r ? (r.addEventListener?.("change", i), () => {
          r.removeEventListener?.(
            "change",
            i
          );
        }) : () => {
        };
      },
      getValue() {
        return r?.matches ?? !1;
      }
    };
  }, [e, t]);
  return To(
    n.subscribe,
    n.getValue,
    () => !1
  );
}
var pc = () => dc("(prefers-reduced-motion: reduce)"), mc = pc;
function ir(t, e) {
  const [n, r] = jt(
    () => [
      (i) => t.subscribe(e, i),
      () => t.get(e)
    ],
    [t, e]
  );
  return To(n, r, r);
}
function fn(...t) {
}
function km(t, e) {
  if (t === e) return !0;
  if (!t || !e || typeof t != "object" || typeof e != "object") return !1;
  const n = Object.keys(t), r = Object.keys(e), { length: i } = n;
  if (r.length !== i) return !1;
  for (const o of n)
    if (t[o] !== e[o])
      return !1;
  return !0;
}
function hc(t, e) {
  if (vc(t)) {
    const n = gc(e) ? e() : e;
    return t(n);
  }
  return t;
}
function vc(t) {
  return typeof t == "function";
}
function gc(t) {
  return typeof t == "function";
}
function Bt(t, e) {
  return typeof Object.hasOwn == "function" ? Object.hasOwn(t, e) : Object.prototype.hasOwnProperty.call(t, e);
}
function ht(...t) {
  return (...e) => {
    for (const n of t)
      typeof n == "function" && n(...e);
  };
}
function Vm(t) {
  return t.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}
function yc(t, e) {
  const n = { ...t };
  for (const r of e)
    Bt(n, r) && delete n[r];
  return n;
}
function bc(t, e) {
  const n = {};
  for (const r of e)
    Bt(t, r) && (n[r] = t[r]);
  return n;
}
function wc(t) {
  return t;
}
function Jt(t, e) {
  if (!t)
    throw typeof e != "string" ? new Error("Invariant failed") : new Error(e);
}
function xc(t) {
  return Object.keys(t);
}
function gn(t, ...e) {
  const n = typeof t == "function" ? t(...e) : t;
  return n == null ? !1 : !n;
}
function Go(t) {
  return t.disabled || t["aria-disabled"] === !0 || t["aria-disabled"] === "true";
}
function $m(t) {
  return t.getAttribute("aria-disabled") === "true" || "disabled" in t && t.disabled === !0;
}
function Uo(t) {
  const e = {};
  for (const n in t)
    t[n] !== void 0 && (e[n] = t[n]);
  return e;
}
function rt(...t) {
  for (const e of t)
    if (e !== void 0) return e;
}
function or(t, e) {
  typeof t == "function" ? t(e) : t && (t.current = e);
}
function Sc(t) {
  return !t || !Fe(t) ? !1 : "ref" in t.props || "ref" in t;
}
function Ec(t) {
  return Sc(t) ? { ...t.props }.ref || t.ref : null;
}
function Ac(t, e) {
  const n = { ...t };
  for (const r in e) {
    if (!Bt(e, r)) continue;
    if (r === "className") {
      const o = "className", s = t[o], a = e[o];
      s && a ? n[o] = `${s} ${a}` : n[o] = a || s;
      continue;
    }
    if (r === "style") {
      const o = "style";
      n[o] = t[o] ? { ...t[o], ...e[o] } : e[o];
      continue;
    }
    const i = e[r];
    if (r.startsWith("on")) {
      if (typeof i != "function")
        continue;
      const o = t[r];
      if (typeof o == "function") {
        n[r] = (...s) => {
          i(...s), o(...s);
        };
        continue;
      }
    }
    n[r] = i;
  }
  return n;
}
var Ee = Pc();
function Pc() {
  var t;
  return typeof window < "u" && !!((t = window.document) != null && t.createElement);
}
function K(t) {
  return t ? "self" in t ? t.document : t.ownerDocument || document : document;
}
function Ve(t) {
  return t ? "self" in t ? t.self : K(t).defaultView || window : self;
}
function Qt(t, e = !1) {
  var n;
  const { activeElement: r } = K(t);
  if (!r?.nodeName)
    return null;
  if (xr(r) && ((n = r.contentDocument) != null && n.body))
    return Qt(
      r.contentDocument.body,
      e
    );
  if (e) {
    const i = r.getAttribute("aria-activedescendant");
    if (i) {
      const o = K(r).getElementById(i);
      if (o)
        return o;
    }
  }
  return r;
}
function Z(t, e) {
  return t === e || t.contains(e);
}
function xr(t) {
  return t.tagName === "IFRAME";
}
function Bm(t) {
  const e = t.tagName.toLowerCase();
  return e === "button" ? !0 : e === "input" && t.type ? Cc.indexOf(t.type) !== -1 : !1;
}
var Cc = [
  "button",
  "color",
  "file",
  "image",
  "reset",
  "submit"
];
function Oc(t) {
  if (typeof t.checkVisibility == "function")
    return t.checkVisibility();
  const e = t;
  return e.offsetWidth > 0 || e.offsetHeight > 0 || t.getClientRects().length > 0;
}
function Sr(t) {
  try {
    const e = t instanceof HTMLInputElement && t.selectionStart !== null, n = t.tagName === "TEXTAREA";
    return e || n || !1;
  } catch {
    return !1;
  }
}
function Hm(t) {
  return t.isContentEditable || Sr(t);
}
function Wm(t) {
  if (Sr(t))
    return t.value;
  if (t.isContentEditable) {
    const e = K(t).createRange();
    return e.selectNodeContents(t), e.toString();
  }
  return "";
}
function jm(t) {
  let e = 0, n = 0;
  if (Sr(t))
    e = t.selectionStart || 0, n = t.selectionEnd || 0;
  else if (t.isContentEditable) {
    const r = K(t).getSelection();
    if (r?.rangeCount && r.anchorNode && Z(t, r.anchorNode) && r.focusNode && Z(t, r.focusNode)) {
      const i = r.getRangeAt(0), o = i.cloneRange();
      o.selectNodeContents(t), o.setEnd(i.startContainer, i.startOffset), e = o.toString().length, o.setEnd(i.endContainer, i.endOffset), n = o.toString().length;
    }
  }
  return { start: e, end: n };
}
function _c(t, e) {
  const n = ["dialog", "menu", "listbox", "tree", "grid"], r = t?.getAttribute("role");
  return r && n.indexOf(r) !== -1 ? r : e;
}
function zm(t, e) {
  const n = {
    menu: "menuitem",
    listbox: "option",
    tree: "treeitem"
  }, r = _c(t);
  return r ? n[r] ?? e : e;
}
function Tc(t) {
  if (!t) return null;
  const e = (n) => n === "auto" || n === "scroll";
  if (t.clientHeight && t.scrollHeight > t.clientHeight) {
    const { overflowY: n } = getComputedStyle(t);
    if (e(n)) return t;
  } else if (t.clientWidth && t.scrollWidth > t.clientWidth) {
    const { overflowX: n } = getComputedStyle(t);
    if (e(n)) return t;
  }
  return Tc(t.parentElement) || document.scrollingElement || document.body;
}
function Gm(t, e) {
  const n = t.map((i, o) => [o, i]);
  let r = !1;
  return n.sort(([i, o], [s, a]) => {
    const u = e(o), c = e(a);
    return u === c || !u || !c ? 0 : Rc(u, c) ? (i > s && (r = !0), -1) : (i < s && (r = !0), 1);
  }), r ? n.map(([i, o]) => o) : t;
}
function Rc(t, e) {
  return !!(e.compareDocumentPosition(t) & Node.DOCUMENT_POSITION_PRECEDING);
}
function Dc() {
  return Ee && !!navigator.maxTouchPoints;
}
function Er() {
  return Ee ? /mac|iphone|ipad|ipod/i.test(navigator.platform) : !1;
}
function Xo() {
  return Ee && Er() && /apple/i.test(navigator.vendor);
}
function Um() {
  return Ee && /firefox\//i.test(navigator.userAgent);
}
function Ic() {
  return Ee && navigator.platform.startsWith("Mac") && !Dc();
}
function Xm(t) {
  return !!(t.currentTarget && !Z(t.currentTarget, t.target));
}
function Pi(t) {
  return t.target === t.currentTarget;
}
function Ym(t) {
  const e = t.currentTarget;
  if (!e) return !1;
  const n = Er();
  if (n && !t.metaKey || !n && !t.ctrlKey) return !1;
  const r = e.tagName.toLowerCase();
  return r === "a" || r === "button" && e.type === "submit" || r === "input" && e.type === "submit";
}
function Km(t) {
  const e = t.currentTarget;
  if (!e) return !1;
  const n = e.tagName.toLowerCase();
  return t.altKey ? n === "a" || n === "button" && e.type === "submit" || n === "input" && e.type === "submit" : !1;
}
function Zm(t, e, n) {
  const r = new Event(e, n);
  return t.dispatchEvent(r);
}
function qm(t, e) {
  const n = new FocusEvent("blur", e), r = t.dispatchEvent(n), i = { ...e, bubbles: !0 };
  return t.dispatchEvent(new FocusEvent("focusout", i)), r;
}
function Jm(t, e, n) {
  const r = new KeyboardEvent(e, n);
  return t.dispatchEvent(r);
}
function Qm(t, e) {
  const n = new MouseEvent("click", e);
  return t.dispatchEvent(n);
}
function de(t, e) {
  const n = e || t.currentTarget, r = t.relatedTarget;
  return !r || !Z(n, r);
}
function Ci(t, e, n, r) {
  const o = ((a) => {
    const u = requestAnimationFrame(a);
    return () => cancelAnimationFrame(u);
  })(() => {
    t.removeEventListener(e, s, !0), n();
  }), s = () => {
    o(), n();
  };
  return t.addEventListener(e, s, { once: !0, capture: !0 }), o;
}
function it(t, e, n, r = window) {
  const i = [];
  try {
    r.document.addEventListener(t, e, n);
    for (const s of Array.from(r.frames))
      i.push(it(t, e, n, s));
  } catch {
  }
  return () => {
    try {
      r.document.removeEventListener(t, e, n);
    } catch {
    }
    for (const s of i)
      s();
  };
}
var Yo = { ...W }, Oi = Yo.useId, _i = Yo.useInsertionEffect, U = Ee ? Mt : V;
function Nc(t) {
  const [e] = Y(t);
  return e;
}
function Ko(t) {
  const e = F(t);
  return U(() => {
    e.current = t;
  }), e;
}
function Q(t) {
  const e = F(() => {
    throw new Error("Cannot call an event handler while rendering.");
  });
  return _i ? _i(() => {
    e.current = t;
  }) : e.current = t, At((...n) => {
    var r;
    return (r = e.current) == null ? void 0 : r.call(e, ...n);
  }, []);
}
function th(t) {
  const [e, n] = Y(null);
  return U(() => {
    if (e == null || !t) return;
    let r = null;
    return t((i) => (r = i, e)), () => {
      t(r);
    };
  }, [e, t]), [e, n];
}
function te(...t) {
  return jt(() => {
    if (t.some(Boolean))
      return (e) => {
        for (const n of t)
          or(n, e);
      };
  }, t);
}
function Zo(t) {
  if (Oi) {
    const r = Oi();
    return t || r;
  }
  const [e, n] = Y(t);
  return U(() => {
    if (t || e) return;
    const r = Math.random().toString(36).slice(2, 8);
    n(`id-${r}`);
  }, [t, e]), t || e;
}
function Mc(t, e) {
  const n = (o) => {
    if (typeof o == "string")
      return o;
  }, [r, i] = Y(() => n(e));
  return U(() => {
    const o = t && "current" in t ? t.current : t;
    i(o?.tagName.toLowerCase() || n(e));
  }, [t, e]), r;
}
function eh(t, e, n) {
  const r = Nc(n), [i, o] = Y(r);
  return V(() => {
    const s = t && "current" in t ? t.current : t;
    if (!s) return;
    const a = () => {
      const c = s.getAttribute(e);
      o(c ?? r);
    }, u = new MutationObserver(a);
    return u.observe(s, { attributeFilter: [e] }), a(), () => u.disconnect();
  }, [t, e, r]), i;
}
function qo(t, e) {
  const n = F(!1);
  V(() => {
    if (n.current)
      return t();
    n.current = !0;
  }, e), V(
    () => () => {
      n.current = !1;
    },
    []
  );
}
function Fc() {
  return ua(() => [], []);
}
function he(t) {
  return Q(
    typeof t == "function" ? t : () => t
  );
}
function xt(t, e, n = []) {
  const r = At(
    (i) => (t.wrapElement && (i = t.wrapElement(i)), e(i)),
    // oxlint-disable-next-line exhaustive-deps
    [...n, t.wrapElement]
  );
  return { ...t, wrapElement: r };
}
function Ar(t = !1, e) {
  const [n, r] = Y(null);
  return { portalRef: te(r, e), portalNode: n, domReady: !t || n };
}
function Lc(t, e, n) {
  const r = t.onLoadedMetadataCapture, i = jt(() => Object.assign(
    () => {
    },
    r,
    ...n !== void 0 ? [{ [e]: n }] : []
  ), [r, e, n]);
  return [r?.[e], { onLoadedMetadataCapture: i }];
}
var Ti = !1;
function Jo() {
  return V(() => {
    Ti || (it("mousemove", Vc, !0), it("mousedown", sn, !0), it("mouseup", sn, !0), it("keydown", sn, !0), it("scroll", sn, !0), Ti = !0);
  }, []), Q(() => Pr);
}
var Pr = !1, Ri = 0, Di = 0;
function kc(t) {
  const e = t.movementX || t.screenX - Ri, n = t.movementY || t.screenY - Di;
  return Ri = t.screenX, Di = t.screenY, e || n || process.env.NODE_ENV === "test";
}
function Vc(t) {
  kc(t) && (Pr = !0);
}
function sn() {
  Pr = !1;
}
function st(t) {
  const e = W.forwardRef(
    // @ts-ignore Incompatible with React 19 types. Ignore for now.
    (n, r) => t({ ...n, ref: r })
  );
  return e.displayName = t.displayName || t.name, e;
}
function nh(t, e) {
  return W.memo(t, e);
}
function ut(t, e) {
  const { wrapElement: n, render: r, ...i } = e, o = te(e.ref, Ec(r));
  let s;
  if (W.isValidElement(r)) {
    const a = {
      // @ts-ignore Incompatible with React 19 types. Ignore for now.
      ...r.props,
      ref: o
    };
    s = W.cloneElement(r, Ac(i, a));
  } else r ? s = r(i) : s = /* @__PURE__ */ C(t, { ...i });
  return n ? n(s) : s;
}
function gt(t) {
  const e = (n = {}) => t(n);
  return e.displayName = t.name, e;
}
function Ge(t = [], e = []) {
  const n = W.createContext(void 0), r = W.createContext(void 0), i = () => W.useContext(n), o = (c = !1) => {
    const l = W.useContext(r), f = i();
    return c ? l : l || f;
  }, s = () => {
    const c = W.useContext(r), l = i();
    if (!(c && c === l))
      return l;
  }, a = (c) => t.reduceRight(
    (l, f) => /* @__PURE__ */ C(f, { ...c, children: l }),
    /* @__PURE__ */ C(n.Provider, { ...c })
  );
  return {
    context: n,
    scopedContext: r,
    useContext: i,
    useScopedContext: o,
    useProviderContext: s,
    ContextProvider: a,
    ScopedContextProvider: (c) => /* @__PURE__ */ C(a, { ...c, children: e.reduceRight(
      (l, f) => /* @__PURE__ */ C(f, { ...c, children: l }),
      /* @__PURE__ */ C(r.Provider, { ...c })
    ) })
  };
}
var Qo = kt(!0), Cr = "input:not([type='hidden']):not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], button:not([disabled]), [tabindex], summary, iframe, object, embed, area[href], audio[controls], video[controls], [contenteditable]:not([contenteditable='false'])";
function $c(t) {
  return Number.parseInt(t.getAttribute("tabindex") || "0", 10) < 0;
}
function It(t) {
  return !(!t.matches(Cr) || !Oc(t) || t.closest("[inert]"));
}
function $e(t) {
  if (!It(t) || $c(t)) return !1;
  if (!("form" in t) || !t.form || t.checked || t.type !== "radio") return !0;
  const e = t.form.elements.namedItem(t.name);
  if (!e || !("length" in e)) return !0;
  const n = Qt(t);
  return !n || n === t || !("form" in n) || n.form !== t.form || n.name !== t.name;
}
function Or(t, e) {
  const n = Array.from(
    t.querySelectorAll(Cr)
  );
  e && n.unshift(t);
  const r = n.filter(It);
  return r.forEach((i, o) => {
    var s;
    if (!xr(i)) return;
    const a = (s = i.contentDocument) == null ? void 0 : s.body;
    a && r.splice(o, 1, ...Or(a));
  }), r;
}
function Cn(t, e, n) {
  const r = Array.from(
    t.querySelectorAll(Cr)
  ), i = r.filter($e);
  return e && $e(t) && i.unshift(t), i.forEach((o, s) => {
    var a;
    if (!xr(o)) return;
    const u = (a = o.contentDocument) == null ? void 0 : a.body;
    if (!u) return;
    const c = Cn(
      u,
      !1,
      n
    );
    i.splice(s, 1, ...c);
  }), !i.length && n ? r : i;
}
function Bc(t, e, n) {
  const [r] = Cn(
    t,
    e,
    n
  );
  return r || null;
}
function Hc(t, e, n, r) {
  const i = Qt(t), o = Or(t, e), s = o.indexOf(i), a = o.slice(s + 1);
  return a.find($e) || (n ? o.find($e) : null) || (r ? a[0] : null) || null;
}
function $n(t, e) {
  return Hc(
    document.body,
    !1,
    t,
    e
  );
}
function Wc(t, e, n, r) {
  const i = Qt(t), o = Or(t, e).reverse(), s = o.indexOf(i);
  return o.slice(s + 1).find($e) || null || null || null;
}
function Ii(t, e) {
  return Wc(
    document.body,
    !1
  );
}
function Ni(t) {
  const e = Qt(t);
  if (!e) return !1;
  if (e === t) return !0;
  const n = e.getAttribute("aria-activedescendant");
  return n ? n === t.id : !1;
}
function jc(t) {
  const e = Qt(t);
  if (!e) return !1;
  if (Z(t, e)) return !0;
  const n = e.getAttribute("aria-activedescendant");
  return !n || !("id" in t) ? !1 : n === t.id ? !0 : !!t.querySelector(`#${CSS.escape(n)}`);
}
function zc(t) {
  const e = t.getAttribute("tabindex") ?? "";
  t.setAttribute("data-tabindex", e), t.setAttribute("tabindex", "-1");
}
function Gc(t, e) {
  const n = Cn(t, e);
  for (const r of n)
    zc(r);
}
function Uc(t) {
  const e = t.querySelectorAll("[data-tabindex]"), n = (r) => {
    const i = r.getAttribute("data-tabindex");
    r.removeAttribute("data-tabindex"), i ? r.setAttribute("tabindex", i) : r.removeAttribute("tabindex");
  };
  t.hasAttribute("data-tabindex") && n(t);
  for (const r of e)
    n(r);
}
function rh(t, e) {
  "scrollIntoView" in t ? (t.focus({ preventScroll: !0 }), t.scrollIntoView({ block: "nearest", inline: "nearest", ...e })) : t.focus();
}
var Xc = "div", Yc = /* @__PURE__ */ Symbol("accessibleWhenDisabled"), Kc = Xo(), Zc = [
  "text",
  "search",
  "url",
  "tel",
  "email",
  "password",
  "number",
  "date",
  "month",
  "week",
  "time",
  "datetime",
  "datetime-local"
];
function qc(t) {
  const { tagName: e, readOnly: n, type: r } = t;
  return e === "TEXTAREA" && !n || e === "SELECT" && !n ? !0 : e === "INPUT" && !n ? Zc.includes(r) : !!(t.isContentEditable || t.getAttribute("role") === "combobox" && t.dataset.name);
}
function Jc(t) {
  return t ? t === "button" || t === "summary" || t === "input" || t === "select" || t === "textarea" || t === "a" : !0;
}
function Qc(t) {
  return t ? t === "button" || t === "input" || t === "select" || t === "textarea" : !0;
}
var tu = [
  "button",
  "color",
  "file",
  "image",
  "reset",
  "submit"
];
function eu(t, e) {
  return t === "button" ? !0 : t === "input" && e ? e === "checkbox" || e === "radio" ? !0 : tu.includes(e) : !1;
}
function nu({
  focusable: t,
  trulyDisabled: e,
  nativeTabbable: n,
  supportsDisabled: r,
  safariTabIndex: i,
  tabIndexProp: o
}) {
  return t ? e ? n && !r ? -1 : void 0 : n ? i && o == null ? 0 : o : o ?? 0 : o;
}
function Bn(t, e) {
  return Q((n) => {
    t?.(n), !n.defaultPrevented && e && (n.stopPropagation(), n.preventDefault());
  });
}
var Mi = !1, _r = !0;
function ru(t) {
  const e = t.target;
  e && "hasAttribute" in e && (e.hasAttribute("data-focus-visible") || (_r = !1));
}
function iu(t) {
  t.metaKey || t.ctrlKey || t.altKey || (_r = !0);
}
var Tr = gt(
  function({
    focusable: e = !0,
    accessibleWhenDisabled: n,
    autoFocus: r,
    onFocusVisible: i,
    ...o
  }) {
    const s = F(null), [a, u] = Lc(
      o,
      Yc,
      n
    );
    n ?? (n = a), V(() => {
      e && (Mi || (it("mousedown", ru, !0), it("keydown", iu, !0), Mi = !0));
    }, [e]);
    const c = e && Go(o), l = c && !n, [f, m] = Y(!1);
    V(() => {
      e && l && f && m(!1);
    }, [e, l, f]), V(() => {
      if (!e || !f) return;
      const P = s.current;
      if (!P || typeof IntersectionObserver > "u") return;
      const N = new IntersectionObserver(() => {
        It(P) || m(!1);
      });
      return N.observe(P), () => N.disconnect();
    }, [e, f]);
    const h = Bn(
      o.onKeyPressCapture,
      c
    ), p = Bn(
      o.onMouseDownCapture,
      c
    ), d = Bn(o.onClickCapture, c), w = (P, N) => {
      if (N && (P.currentTarget = N), !e) return;
      const L = P.currentTarget;
      L && Ni(L) && (i?.(P), !P.defaultPrevented && (L.dataset.focusVisible = "true", m(!0)));
    }, x = o.onKeyDownCapture, v = Q((P) => {
      if (x?.(P), P.defaultPrevented || !e || f || P.metaKey || P.altKey || P.ctrlKey || !Pi(P)) return;
      const N = P.currentTarget;
      Ci(N, "focusout", () => w(P, N));
    }), b = o.onFocusCapture, g = Q((P) => {
      if (b?.(P), P.defaultPrevented || !e) return;
      if (!Pi(P)) {
        m(!1);
        return;
      }
      const N = P.currentTarget, L = () => w(P, N);
      _r || qc(P.target) ? Ci(P.target, "focusout", L) : m(!1);
    }), y = o.onBlur, E = Q((P) => {
      y?.(P), e && de(P) && (P.currentTarget.removeAttribute("data-focus-visible"), m(!1));
    }), S = vt(Qo), A = Q((P) => {
      e && r && P && S && queueMicrotask(() => {
        Ni(P) || It(P) && P.focus();
      });
    }), _ = Mc(s), T = e && Jc(_), O = e && Qc(_), [M, D] = Y(!1);
    Kc && V(() => {
      if (!e) return;
      const P = s.current;
      if (!P) return;
      const N = P.tagName.toLowerCase(), L = P.type;
      D(eu(N, L));
    }, [e]);
    const I = o.style, k = jt(() => l ? { pointerEvents: "none", ...I } : I, [l, I]);
    return o = {
      "data-focus-visible": e && f || void 0,
      "data-autofocus": r || void 0,
      "aria-disabled": c || void 0,
      ...o,
      ...u,
      ref: te(s, A, o.ref),
      style: k,
      tabIndex: nu({
        focusable: e,
        trulyDisabled: l,
        nativeTabbable: T,
        supportsDisabled: O,
        safariTabIndex: M,
        tabIndexProp: o.tabIndex
      }),
      disabled: O && l ? !0 : void 0,
      // TODO: Test Focusable contentEditable.
      contentEditable: c ? void 0 : o.contentEditable,
      onKeyPressCapture: h,
      onClickCapture: d,
      onMouseDownCapture: p,
      onKeyDownCapture: v,
      onFocusCapture: g,
      onBlur: E
    }, Uo(o);
  }
);
st(function(e) {
  const n = Tr(e);
  return ut(Xc, n);
});
function ce(t, e) {
  const n = t.__unstableInternals;
  return Jt(n, "Invalid store"), n[e];
}
function Yt(t, ...e) {
  let n = t, r = n, i = /* @__PURE__ */ Symbol(), o = fn;
  const s = /* @__PURE__ */ new Set(), a = /* @__PURE__ */ new Set(), u = /* @__PURE__ */ new Set(), c = /* @__PURE__ */ new Set(), l = /* @__PURE__ */ new Set(), f = /* @__PURE__ */ new WeakMap(), m = /* @__PURE__ */ new WeakMap(), h = (A) => (u.add(A), () => u.delete(A)), p = () => {
    const A = s.size, _ = /* @__PURE__ */ Symbol();
    s.add(_);
    const T = () => {
      s.delete(_), !s.size && o();
    };
    if (A) return T;
    const O = xc(n).map(
      (I) => ht(
        ...e.map((k) => {
          var P;
          const N = (P = k?.getState) == null ? void 0 : P.call(k);
          if (N && Bt(N, I))
            return Ht(k, [I], (L) => {
              E(
                I,
                L[I],
                // @ts-expect-error - Not public API. This is just to prevent
                // infinite loops.
                !0
              );
            });
        })
      )
    ), M = [];
    for (const I of u)
      M.push(I());
    const D = e.map(ts);
    return o = ht(...O, ...M, ...D), T;
  }, d = (A, _, T = c) => (T.add(_), m.set(_, A), () => {
    var O;
    (O = f.get(_)) == null || O(), f.delete(_), m.delete(_), T.delete(_);
  }), w = (A, _) => d(A, _), x = (A, _) => (f.set(_, _(n, n)), d(A, _)), v = (A, _) => (f.set(_, _(n, r)), d(A, _, l)), b = (A) => Yt(bc(n, A), S), g = (A) => Yt(yc(n, A), S), y = () => n, E = (A, _, T = !1) => {
    var O;
    if (!Bt(n, A)) return;
    const M = hc(_, n[A]);
    if (M === n[A]) return;
    if (!T)
      for (const P of e)
        (O = P?.setState) == null || O.call(P, A, M);
    const D = n;
    n = { ...n, [A]: M };
    const I = /* @__PURE__ */ Symbol();
    i = I, a.add(A);
    const k = (P, N, L) => {
      var z;
      const at = m.get(P), yt = (tt) => L ? L.has(tt) : tt === A;
      (!at || at.some(yt)) && ((z = f.get(P)) == null || z(), f.set(P, P(n, N)));
    };
    for (const P of c)
      k(P, D);
    queueMicrotask(() => {
      if (i !== I) return;
      const P = n;
      for (const N of l)
        k(N, r, a);
      r = P, a.clear();
    });
  }, S = {
    getState: y,
    setState: E,
    __unstableInternals: {
      setup: h,
      init: p,
      subscribe: w,
      sync: x,
      batch: v,
      pick: b,
      omit: g
    }
  };
  return S;
}
function Hn(t, ...e) {
  if (t)
    return ce(t, "setup")(...e);
}
function ts(t, ...e) {
  if (t)
    return ce(t, "init")(...e);
}
function Rr(t, ...e) {
  if (t)
    return ce(t, "subscribe")(...e);
}
function Ht(t, ...e) {
  if (t)
    return ce(t, "sync")(...e);
}
function ou(t, ...e) {
  if (t)
    return ce(t, "batch")(...e);
}
function es(t, ...e) {
  if (t)
    return ce(t, "omit")(...e);
}
function ih(t, ...e) {
  if (t)
    return ce(t, "pick")(...e);
}
function ns(...t) {
  var e;
  const n = {};
  for (const i of t) {
    const o = (e = i?.getState) == null ? void 0 : e.call(i);
    o && Object.assign(n, o);
  }
  const r = Yt(n, ...t);
  return Object.assign({}, ...t, r);
}
function rs(t, e) {
  if (process.env.NODE_ENV === "production" || !e) return;
  const n = Object.entries(t).filter(([o, s]) => o.startsWith("default") && s !== void 0).map(([o]) => {
    var s;
    const a = o.replace("default", "");
    return `${((s = a[0]) == null ? void 0 : s.toLowerCase()) || ""}${a.slice(1)}`;
  });
  if (!n.length) return;
  const r = e.getState();
  if (n.filter(
    (o) => Bt(r, o)
  ).length)
    throw new Error(
      `Passing a store prop in conjunction with a default state is not supported.

const store = useSelectStore();
<SelectProvider store={store} defaultValue="Apple" />
                ^             ^

Instead, pass the default state to the topmost store:

const store = useSelectStore({ defaultValue: "Apple" });
<SelectProvider store={store} />

See https://github.com/ariakit/ariakit/pull/2745 for more details.

If there's a particular need for this, please submit a feature request at https://github.com/ariakit/ariakit
`
    );
}
var sr = { exports: {} }, Wn = {};
/**
 * @license React
 * use-sync-external-store-shim.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Fi;
function su() {
  if (Fi) return Wn;
  Fi = 1;
  var t = _o;
  function e(f, m) {
    return f === m && (f !== 0 || 1 / f === 1 / m) || f !== f && m !== m;
  }
  var n = typeof Object.is == "function" ? Object.is : e, r = t.useState, i = t.useEffect, o = t.useLayoutEffect, s = t.useDebugValue;
  function a(f, m) {
    var h = m(), p = r({ inst: { value: h, getSnapshot: m } }), d = p[0].inst, w = p[1];
    return o(
      function() {
        d.value = h, d.getSnapshot = m, u(d) && w({ inst: d });
      },
      [f, h, m]
    ), i(
      function() {
        return u(d) && w({ inst: d }), f(function() {
          u(d) && w({ inst: d });
        });
      },
      [f]
    ), s(h), h;
  }
  function u(f) {
    var m = f.getSnapshot;
    f = f.value;
    try {
      var h = m();
      return !n(f, h);
    } catch {
      return !0;
    }
  }
  function c(f, m) {
    return m();
  }
  var l = typeof window > "u" || typeof window.document > "u" || typeof window.document.createElement > "u" ? c : a;
  return Wn.useSyncExternalStore = t.useSyncExternalStore !== void 0 ? t.useSyncExternalStore : l, Wn;
}
var jn = {};
/**
 * @license React
 * use-sync-external-store-shim.development.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Li;
function au() {
  return Li || (Li = 1, process.env.NODE_ENV !== "production" && function() {
    function t(h, p) {
      return h === p && (h !== 0 || 1 / h === 1 / p) || h !== h && p !== p;
    }
    function e(h, p) {
      l || i.startTransition === void 0 || (l = !0, console.error(
        "You are using an outdated, pre-release alpha of React 18 that does not support useSyncExternalStore. The use-sync-external-store shim will not work correctly. Upgrade to a newer pre-release."
      ));
      var d = p();
      if (!f) {
        var w = p();
        o(d, w) || (console.error(
          "The result of getSnapshot should be cached to avoid an infinite loop"
        ), f = !0);
      }
      w = s({
        inst: { value: d, getSnapshot: p }
      });
      var x = w[0].inst, v = w[1];
      return u(
        function() {
          x.value = d, x.getSnapshot = p, n(x) && v({ inst: x });
        },
        [h, d, p]
      ), a(
        function() {
          return n(x) && v({ inst: x }), h(function() {
            n(x) && v({ inst: x });
          });
        },
        [h]
      ), c(d), d;
    }
    function n(h) {
      var p = h.getSnapshot;
      h = h.value;
      try {
        var d = p();
        return !o(h, d);
      } catch {
        return !0;
      }
    }
    function r(h, p) {
      return p();
    }
    typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ < "u" && typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStart == "function" && __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStart(Error());
    var i = _o, o = typeof Object.is == "function" ? Object.is : t, s = i.useState, a = i.useEffect, u = i.useLayoutEffect, c = i.useDebugValue, l = !1, f = !1, m = typeof window > "u" || typeof window.document > "u" || typeof window.document.createElement > "u" ? r : e;
    jn.useSyncExternalStore = i.useSyncExternalStore !== void 0 ? i.useSyncExternalStore : m, typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ < "u" && typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStop == "function" && __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStop(Error());
  }()), jn;
}
process.env.NODE_ENV === "production" ? sr.exports = su() : sr.exports = au();
var is = sr.exports, os = () => () => {
};
function $(t, e = wc) {
  const n = W.useCallback(
    (i) => t ? Rr(t, null, i) : os(),
    [t]
  ), r = () => {
    const i = typeof e == "string" ? e : null, o = typeof e == "function" ? e : null, s = t?.getState();
    if (o) return o(s);
    if (s && i && Bt(s, i))
      return s[i];
  };
  return is.useSyncExternalStore(n, r, r);
}
function oh(t, e) {
  const n = W.useRef(
    {}
  ), r = W.useCallback(
    (o) => t ? Rr(t, null, o) : os(),
    [t]
  ), i = () => {
    const o = t?.getState();
    let s = !1;
    const a = n.current;
    for (const u in e) {
      const c = e[u];
      if (typeof c == "function") {
        const l = c(o);
        l !== a[u] && (a[u] = l, s = !0);
      }
      if (typeof c == "string") {
        if (!o || !Bt(o, c)) continue;
        const l = o[c];
        l !== a[u] && (a[u] = l, s = !0);
      }
    }
    return s && (n.current = { ...a }), n.current;
  };
  return is.useSyncExternalStore(r, i, i);
}
function $t(t, e, n, r) {
  const i = Bt(e, n) ? e[n] : void 0, o = r ? e[r] : void 0, s = Ko({ value: i, setValue: o });
  U(() => Ht(t, [n], (a, u) => {
    const { value: c, setValue: l } = s.current;
    l && a[n] !== u[n] && a[n] !== c && l(a[n]);
  }), [t, n]), U(() => {
    if (i !== void 0)
      return t.setState(n, i), ou(t, [n], () => {
        i !== void 0 && t.setState(n, i);
      });
  });
}
function Dr(t, e) {
  const [n, r] = W.useState(() => t(e));
  U(() => ts(n), [n]);
  const i = W.useCallback(
    (a) => $(n, a),
    [n]
  ), o = W.useMemo(
    () => ({ ...n, useState: i }),
    [n, i]
  ), s = Q(() => {
    r((a) => t({ ...e, ...a.getState() }));
  });
  return [o, s];
}
var Ir = Ge(), ss = Ir.useProviderContext, cu = Ir.ContextProvider, uu = Ir.ScopedContextProvider, Nr = Ge(
  [cu],
  [uu]
), Mr = Nr.useProviderContext, lu = Nr.ContextProvider, Fr = Nr.ScopedContextProvider, fu = kt(void 0), du = kt(void 0), pu = "div";
function ki(t, e) {
  const n = setTimeout(e, t);
  return () => clearTimeout(n);
}
function mu(t) {
  let e = requestAnimationFrame(() => {
    e = requestAnimationFrame(t);
  });
  return () => cancelAnimationFrame(e);
}
function Vi(...t) {
  return t.join(", ").split(", ").reduce((e, n) => {
    const r = n.endsWith("ms") ? 1 : 1e3, i = Number.parseFloat(n || "0s") * r;
    return i > e ? i : e;
  }, 0);
}
function as(t, e, n) {
  return !n && e !== !1 && (!t || !!e);
}
var Lr = gt(function({ store: e, alwaysVisible: n, ...r }) {
  const i = ss();
  e = e || i, Jt(
    e,
    process.env.NODE_ENV !== "production" && "DisclosureContent must receive a `store` prop or be wrapped in a DisclosureProvider component."
  );
  const o = F(null), s = Zo(r.id), [a, u] = Y(null), c = $(e, "open"), l = $(e, "mounted"), f = $(e, "animated"), m = $(e, "contentElement"), h = $(e.disclosure, "contentElement"), p = F(!1);
  U(() => {
    o.current && e?.setContentElement(o.current);
  }, [e]), U(() => {
    let v;
    return e?.setState("animated", (b) => (v = b, !0)), () => {
      v !== void 0 && e?.setState("animated", v);
    };
  }, [e]), U(() => {
    if (!f) {
      c ? p.current && (p.current = !1, u("enter")) : (p.current = !0, u(null));
      return;
    }
    if (!m?.isConnected) {
      u(null);
      return;
    }
    return mu(() => {
      u(c ? "enter" : l ? "leave" : null);
    });
  }, [f, m, c, l]), U(() => {
    if (!e || !f || !a || !m) return;
    const v = () => e?.setState("animating", !1), b = () => Ro(v);
    if (a === "leave" && c || a === "enter" && !c) return;
    if (typeof f == "number")
      return ki(f, b);
    const {
      transitionDuration: g,
      animationDuration: y,
      transitionDelay: E,
      animationDelay: S
    } = getComputedStyle(m), {
      transitionDuration: A = "0",
      animationDuration: _ = "0",
      transitionDelay: T = "0",
      animationDelay: O = "0"
    } = h ? getComputedStyle(h) : {}, M = Vi(
      E,
      S,
      T,
      O
    ), D = Vi(
      g,
      y,
      A,
      _
    ), I = M + D;
    if (!I) {
      a === "enter" && e.setState("animated", !1), v();
      return;
    }
    const k = 1e3 / 60, P = Math.max(I - k, 0);
    return ki(P, b);
  }, [e, f, m, h, c, a]), r = xt(
    r,
    (v) => /* @__PURE__ */ C(Fr, { value: e, children: v }),
    [e]
  );
  const d = as(l, r.hidden, n), w = r.style, x = jt(() => d ? { ...w, display: "none" } : w, [d, w]);
  return r = {
    "data-open": c || void 0,
    "data-enter": a === "enter" || void 0,
    "data-leave": a === "leave" || void 0,
    hidden: d,
    ...r,
    id: s,
    ref: te(s ? e.setContentElement : null, o, r.ref),
    style: x
  }, Uo(r);
}), hu = st(function(e) {
  const n = Lr(e);
  return ut(pu, n);
});
st(function({
  unmountOnHide: e,
  ...n
}) {
  const r = ss(), i = n.store || r;
  return $(
    i,
    (s) => !e || s?.mounted
  ) === !1 ? null : /* @__PURE__ */ C(hu, { ...n });
});
function cs(t = {}) {
  const e = ns(
    t.store,
    es(t.disclosure, ["contentElement", "disclosureElement"])
  );
  rs(t, e);
  const n = e?.getState(), r = rt(
    t.open,
    n?.open,
    t.defaultOpen,
    !1
  ), i = rt(t.animated, n?.animated, !1), o = {
    open: r,
    animated: i,
    animating: !!i && r,
    mounted: r,
    contentElement: rt(n?.contentElement, null),
    disclosureElement: rt(n?.disclosureElement, null)
  }, s = Yt(o, e);
  return Hn(
    s,
    () => Ht(s, ["animated", "animating"], (a) => {
      a.animated || s.setState("animating", !1);
    })
  ), Hn(
    s,
    () => Rr(s, ["open"], () => {
      s.getState().animated && s.setState("animating", !0);
    })
  ), Hn(
    s,
    () => Ht(s, ["open", "animating"], (a) => {
      s.setState("mounted", a.open || a.animating);
    })
  ), {
    ...s,
    disclosure: t.disclosure,
    setOpen: (a) => s.setState("open", a),
    show: () => s.setState("open", !0),
    hide: () => s.setState("open", !1),
    toggle: () => s.setState("open", (a) => !a),
    stopAnimation: () => s.setState("animating", !1),
    setContentElement: (a) => s.setState("contentElement", a),
    setDisclosureElement: (a) => s.setState("disclosureElement", a)
  };
}
function us(t, e, n) {
  return qo(e, [n.store, n.disclosure]), $t(t, n, "open", "setOpen"), $t(t, n, "mounted", "setMounted"), $t(t, n, "animated"), Object.assign(t, { disclosure: n.disclosure });
}
function vu(t = {}) {
  const [e, n] = Dr(cs, t);
  return us(e, n, t);
}
var Ue = Ge(
  [lu],
  [Fr]
), sh = Ue.useContext;
Ue.useScopedContext;
var ls = Ue.useProviderContext, gu = Ue.ContextProvider, fs = Ue.ScopedContextProvider, Xe = Ge(
  [gu],
  [fs]
);
Xe.useContext;
Xe.useScopedContext;
var kr = Xe.useProviderContext, yu = Xe.ContextProvider, ds = Xe.ScopedContextProvider, ps = Ge(
  [yu],
  [ds]
), Vr = ps.useProviderContext, bu = ps.ScopedContextProvider;
function zn(t) {
  return [t.clientX, t.clientY];
}
function $i(t, e) {
  const [n, r] = t;
  let i = !1;
  const o = e.length;
  for (let s = o, a = 0, u = s - 1; a < s; u = a++) {
    const c = e[a], l = e[u], f = e[u === 0 ? s - 1 : u - 1];
    if (c == null || l == null || f == null) return !1;
    const [m, h] = c, [p, d] = l, [, w] = f, x = (h - d) * (n - m) - (m - p) * (r - h);
    if (d < h) {
      if (r >= d && r < h) {
        if (x === 0) return !0;
        x > 0 && (r === d ? r > w && (i = !i) : i = !i);
      }
    } else if (h < d) {
      if (r > h && r <= d) {
        if (x === 0) return !0;
        x < 0 && (r === d ? r < w && (i = !i) : i = !i);
      }
    } else if (r === h && (n >= p && n <= m || n >= m && n <= p))
      return !0;
  }
  return i;
}
function wu(t, e) {
  const { top: n, right: r, bottom: i, left: o } = e, [s, a] = t, u = s < o ? "left" : s > r ? "right" : null, c = a < n ? "top" : a > i ? "bottom" : null;
  return [u, c];
}
function Bi(t, e) {
  const n = t.getBoundingClientRect(), { top: r, right: i, bottom: o, left: s } = n, [a, u] = wu(e, n), c = [e];
  return a ? (u !== "top" && c.push([a === "left" ? s : i, r]), c.push([a === "left" ? i : s, r]), c.push([a === "left" ? i : s, o]), u !== "bottom" && c.push([a === "left" ? s : i, o])) : u === "top" ? (c.push([s, r]), c.push([s, o]), c.push([i, o]), c.push([i, r])) : (c.push([s, o]), c.push([s, r]), c.push([i, r]), c.push([i, o])), c;
}
var Hi = kt(null), xu = "span", ms = gt(
  function(e) {
    return e = {
      ...e,
      style: {
        border: 0,
        clip: "rect(0 0 0 0)",
        height: "1px",
        margin: "-1px",
        overflow: "hidden",
        padding: 0,
        position: "absolute",
        whiteSpace: "nowrap",
        width: "1px",
        ...e.style
      }
    }, e;
  }
);
st(function(e) {
  const n = ms(e);
  return ut(xu, n);
});
var Su = "span", Eu = gt(
  function(e) {
    return e = {
      "data-focus-trap": "",
      tabIndex: 0,
      "aria-hidden": !0,
      ...e,
      style: {
        // Prevents unintended scroll jumps.
        position: "fixed",
        top: 0,
        left: 0,
        ...e.style
      }
    }, e = ms(e), e;
  }
), an = st(function(e) {
  const n = Eu(e);
  return ut(Su, n);
}), Au = "div";
function Wi(t) {
  const e = K(t), { fullscreenElement: n } = e;
  return n instanceof HTMLElement ? n : e.body;
}
function Pu(t, e) {
  return e ? typeof e == "function" ? e(t) : e : K(t).createElement("div");
}
function Cu(t = "id") {
  return `${t ? `${t}-` : ""}${Math.random().toString(36).slice(2, 8)}`;
}
function Xt(t) {
  queueMicrotask(() => {
    t?.focus();
  });
}
var hs = gt(function({
  preserveTabOrder: e,
  preserveTabOrderAnchor: n,
  portalElement: r,
  portalRef: i,
  portal: o = !0,
  ...s
}) {
  const a = F(null), u = te(a, s.ref), c = vt(Hi), [l, f] = Y(null), [m, h] = Y(
    null
  ), p = F(null), d = F(null), w = F(null), x = F(null);
  return U(() => {
    const v = a.current;
    if (!v || !o) {
      f(null);
      return;
    }
    const b = Pu(v, r);
    if (!b) {
      f(null);
      return;
    }
    const g = b.isConnected;
    if (g || (c || Wi(v)).appendChild(b), b.id || (b.id = v.id ? `portal/${v.id}` : Cu()), f(b), or(i, b), !g)
      return () => {
        b.remove(), or(i, null);
      };
  }, [o, r, c, i]), V(() => {
    if (!l || c || r) return;
    const v = K(l), b = () => {
      const g = Wi(l);
      l.parentElement !== g && g.appendChild(l);
    };
    return b(), v.addEventListener("fullscreenchange", b), () => {
      v.removeEventListener("fullscreenchange", b);
    };
  }, [l, c, r]), U(() => {
    if (!o || !e || !n) return;
    const b = K(n).createElement("span");
    return b.style.position = "fixed", n.insertAdjacentElement("afterend", b), h(b), () => {
      b.remove(), h(null);
    };
  }, [o, e, n]), V(() => {
    if (!l || !e) return;
    let v = 0;
    const b = (g) => {
      if (!de(g)) return;
      const y = g.type === "focusin";
      if (cancelAnimationFrame(v), y)
        return Uc(l);
      v = requestAnimationFrame(() => {
        Gc(l, !0);
      });
    };
    return l.addEventListener("focusin", b, !0), l.addEventListener("focusout", b, !0), () => {
      cancelAnimationFrame(v), l.removeEventListener("focusin", b, !0), l.removeEventListener("focusout", b, !0);
    };
  }, [l, e]), s = xt(
    s,
    (v) => {
      if (v = // While the portal node is not in the DOM, we need to pass the
      // current context to the portal context, otherwise it's going to
      // reset to the body element on nested portals.
      /* @__PURE__ */ C(Hi.Provider, { value: l || c, children: v }), !o) return v;
      if (!l)
        return /* @__PURE__ */ C(
          "span",
          {
            ref: u,
            id: s.id,
            style: { position: "fixed" },
            hidden: !0
          }
        );
      v = /* @__PURE__ */ Et(St, { children: [
        e && l && /* @__PURE__ */ C(
          an,
          {
            ref: d,
            "data-focus-trap": s.id,
            className: "__focus-trap-inner-before",
            onFocus: (g) => {
              de(g, l) ? Xt($n()) : Xt(p.current);
            }
          }
        ),
        v,
        e && l && /* @__PURE__ */ C(
          an,
          {
            ref: w,
            "data-focus-trap": s.id,
            className: "__focus-trap-inner-after",
            onFocus: (g) => {
              de(g, l) ? Xt(Ii()) : Xt(x.current);
            }
          }
        )
      ] }), l && (v = Qn(v, l));
      let b = /* @__PURE__ */ Et(St, { children: [
        e && l && /* @__PURE__ */ C(
          an,
          {
            ref: p,
            "data-focus-trap": s.id,
            className: "__focus-trap-outer-before",
            onFocus: (g) => {
              !(g.relatedTarget === x.current) && de(g, l) ? Xt(d.current) : Xt(Ii());
            }
          }
        ),
        e && // We're using position: fixed here so that the browser doesn't
        // add margin to the element when setting gap on a parent element.
        /* @__PURE__ */ C("span", { "aria-owns": l?.id, style: { position: "fixed" } }),
        e && l && /* @__PURE__ */ C(
          an,
          {
            ref: x,
            "data-focus-trap": s.id,
            className: "__focus-trap-outer-after",
            onFocus: (g) => {
              if (de(g, l))
                Xt(w.current);
              else {
                const y = $n();
                if (y === d.current) {
                  requestAnimationFrame(() => {
                    var E;
                    return (E = $n()) == null ? void 0 : E.focus();
                  });
                  return;
                }
                Xt(y);
              }
            }
          }
        )
      ] });
      return m && e && (b = Qn(
        b,
        m
      )), /* @__PURE__ */ Et(St, { children: [
        b,
        v
      ] });
    },
    [l, c, o, s.id, e, m]
  ), s = {
    ...s,
    ref: u
  }, s;
});
st(function(e) {
  const n = hs(e);
  return ut(Au, n);
});
var ji = kt(0);
function Ou({ level: t, children: e }) {
  const n = vt(ji), r = Math.max(
    Math.min(t || n + 1, 6),
    1
  );
  return /* @__PURE__ */ C(ji.Provider, { value: r, children: e });
}
var _u = "div", vs = gt(function({ autoFocusOnShow: e = !0, ...n }) {
  return n = xt(
    n,
    (r) => /* @__PURE__ */ C(Qo.Provider, { value: e, children: r }),
    [e]
  ), n;
});
st(function(e) {
  const n = vs(e);
  return ut(_u, n);
});
function Tu(t, e) {
  const r = K(t).createElement("button");
  return r.type = "button", r.tabIndex = -1, r.textContent = "Dismiss popup", Object.assign(r.style, {
    border: "0px",
    clip: "rect(0 0 0 0)",
    height: "1px",
    margin: "-1px",
    overflow: "hidden",
    padding: "0px",
    position: "absolute",
    whiteSpace: "nowrap",
    width: "1px"
  }), r.addEventListener("click", e), t.prepend(r), () => {
    r.removeEventListener("click", e), r.remove();
  };
}
function Ru(t, e) {
  const n = F(null);
  return V(() => {
    if (!t) {
      n.current = null;
      return;
    }
    return it("mousedown", (i) => {
      n.current = i.target;
    }, !0, e);
  }, [t, e]), n;
}
var Gn = /* @__PURE__ */ new WeakMap();
function Ye(t, e, n) {
  Gn.has(t) || Gn.set(t, /* @__PURE__ */ new Map());
  const r = Gn.get(t), i = r.get(e);
  if (!i)
    return r.set(e, n()), () => {
      var a;
      (a = r.get(e)) == null || a(), r.delete(e);
    };
  const o = n(), s = () => {
    o(), i(), r.delete(e);
  };
  return r.set(e, s), () => {
    r.get(e) === s && (o(), r.set(e, i));
  };
}
function $r(t, e, n) {
  return Ye(t, e, () => {
    const i = t.getAttribute(e);
    return t.setAttribute(e, n), () => {
      i == null ? t.removeAttribute(e) : t.setAttribute(e, i);
    };
  });
}
function se(t, e, n) {
  return Ye(t, e, () => {
    const i = e in t, o = t[e];
    return t[e] = n, () => {
      i ? t[e] = o : delete t[e];
    };
  });
}
function ar(t, e) {
  return t ? Ye(t, "style", () => {
    const r = t.style.cssText;
    return Object.assign(t.style, e), () => {
      t.style.cssText = r;
    };
  }) : () => {
  };
}
function Du(t, e, n) {
  return t ? Ye(t, e, () => {
    const i = t.style.getPropertyValue(e);
    return t.style.setProperty(e, n), () => {
      i ? t.style.setProperty(e, i) : t.style.removeProperty(e);
    };
  }) : () => {
  };
}
var Iu = ["SCRIPT", "STYLE"];
function cr(t) {
  return `__ariakit-dialog-snapshot-${t}`;
}
function Nu(t, e) {
  const n = K(e), r = cr(t);
  if (!n.body[r]) return !0;
  do {
    if (e === n.body) return !1;
    if (e[r]) return !0;
    if (!e.parentElement) return !1;
    e = e.parentElement;
  } while (!0);
}
function Mu(t, e, n) {
  return Iu.includes(e.tagName) || !Nu(t, e) ? !1 : !n.some(
    (r) => r && Z(e, r)
  );
}
function Br(t, e, n, r) {
  for (let i of e) {
    if (!i?.isConnected) continue;
    const o = e.some((u) => !u || u === i ? !1 : u.contains(i)), s = K(i), a = i;
    for (; i.parentElement && i !== s.body; ) {
      if (r?.(i.parentElement, a), !o)
        for (const u of i.parentElement.children)
          Mu(t, u, e) && n(u, a);
      i = i.parentElement;
    }
  }
}
function Fu(t, e) {
  const { body: n } = K(e[0]), r = [];
  return Br(t, e, (o) => {
    r.push(se(o, cr(t), !0));
  }), ht(se(n, cr(t), !0), () => {
    for (const o of r)
      o();
  });
}
function gs(t, ...e) {
  if (!t) return !1;
  const n = t.getAttribute("data-backdrop");
  return n == null ? !1 : n === "" || n === "true" || !e.length ? !0 : e.some((r) => n === r);
}
function ye(t = "", e = !1) {
  return `__ariakit-dialog-${e ? "ancestor" : "outside"}${t ? `-${t}` : ""}`;
}
function Lu(t, e = "") {
  return ht(
    se(t, ye(), !0),
    se(t, ye(e), !0)
  );
}
function ys(t, e = "") {
  return ht(
    se(t, ye("", !0), !0),
    se(t, ye(e, !0), !0)
  );
}
function Hr(t, e) {
  const n = ye(e, !0);
  if (t[n]) return !0;
  const r = ye(e);
  do {
    if (t[r]) return !0;
    if (!t.parentElement) return !1;
    t = t.parentElement;
  } while (!0);
}
function zi(t, e) {
  const n = [], r = e.map((o) => o?.id);
  return Br(
    t,
    e,
    (o) => {
      gs(o, ...r) || n.unshift(Lu(o, t));
    },
    (o, s) => {
      s.hasAttribute("data-dialog") && s.id !== t || n.unshift(ys(o, t));
    }
  ), () => {
    for (const o of n)
      o();
  };
}
function ku(t) {
  return t.tagName === "HTML" ? !0 : Z(K(t).body, t);
}
function Vu(t, e) {
  if (!t) return !1;
  if (Z(t, e)) return !0;
  const n = e.getAttribute("aria-activedescendant");
  if (n) {
    const r = K(t).getElementById(n);
    if (r)
      return Z(t, r);
  }
  return !1;
}
function $u(t, e) {
  if (!("clientY" in t)) return !1;
  const n = e.getBoundingClientRect();
  return n.width === 0 || n.height === 0 ? !1 : n.top <= t.clientY && t.clientY <= n.top + n.height && n.left <= t.clientX && t.clientX <= n.left + n.width;
}
function Un({
  store: t,
  type: e,
  listener: n,
  capture: r,
  domReady: i
}) {
  const o = Q(n), s = $(t, "open"), a = $(t, "contentElement"), u = F(!1);
  U(() => {
    if (!s || !i || !a) return;
    const c = () => {
      u.current = !0;
    };
    return a.addEventListener("focusin", c, !0), () => a.removeEventListener("focusin", c, !0);
  }, [s, i, a]), V(() => {
    if (!s) return;
    const c = (f) => {
      const { contentElement: m, disclosureElement: h } = t.getState(), p = f.target;
      !m || !p || !ku(p) || Z(m, p) || Vu(h, p) || p.hasAttribute("data-focus-trap") || $u(f, m) || u.current && !Hr(p, m.id) || o(f);
    }, l = a ? Ve(a) : void 0;
    return it(e, c, r, l);
  }, [s, r, t, e, o, a]);
}
function Xn(t, e) {
  return typeof t == "function" ? t(e) : !!t;
}
function Bu(t, e, n, r) {
  const i = $(t, "open"), o = $(t, "contentElement"), s = o ? Ve(o) : void 0, a = Ru(i, s), u = { store: t, domReady: n, capture: !0 };
  Un({
    ...u,
    type: "click",
    listener: (c) => {
      const { contentElement: l } = t.getState(), f = a.current;
      f && Hr(f, l?.id) && Xn(e, c) && (r && (r.current = !0), t.hide());
    }
  }), Un({
    ...u,
    type: "focusin",
    listener: (c) => {
      const { contentElement: l } = t.getState();
      l && c.target !== K(l) && Xn(e, c) && t.hide();
    }
  }), Un({
    ...u,
    type: "contextmenu",
    listener: (c) => {
      Xn(e, c) && (r && (r.current = !0), t.hide());
    }
  });
}
var Gi = kt({});
function Hu(t) {
  const e = vt(Gi), [n, r] = Y([]), i = At(
    (a) => {
      var u;
      return r((c) => [...c, a]), ht((u = e.add) == null ? void 0 : u.call(e, a), () => {
        r((c) => c.filter((l) => l !== a));
      });
    },
    [e]
  );
  U(() => Ht(t, ["open", "contentElement"], (a) => {
    var u;
    if (a.open && a.contentElement)
      return (u = e.add) == null ? void 0 : u.call(e, t);
  }), [t, e]);
  const o = jt(() => ({ store: t, add: i }), [t, i]);
  return { wrapElement: At(
    (a) => /* @__PURE__ */ C(Gi.Provider, { value: o, children: a }),
    [o]
  ), nestedDialogs: n };
}
function Wu({
  attribute: t,
  contentId: e,
  contentElement: n,
  enabled: r
}) {
  const [i, o] = Fc(), s = At(() => {
    if (!r || !n) return !1;
    const { body: a } = K(n), u = a.getAttribute(t);
    return !u || u === e;
  }, [i, r, n, t, e]);
  return V(() => {
    if (!r || !e || !n) return;
    const { body: a } = K(n);
    if (s())
      return a.setAttribute(t, e), () => a.removeAttribute(t);
    const u = new MutationObserver(() => Ro(o));
    return u.observe(a, { attributeFilter: [t] }), () => u.disconnect();
  }, [i, r, e, n, s, t]), s;
}
function ju(t) {
  const e = t.getBoundingClientRect().left;
  return Math.round(e) + t.scrollLeft ? "paddingLeft" : "paddingRight";
}
function zu(t, e, n) {
  const r = Wu({
    attribute: "data-dialog-prevent-body-scroll",
    contentElement: t,
    contentId: e,
    enabled: n
  });
  V(() => {
    if (!r() || !t) return;
    const i = K(t), o = Ve(t), { documentElement: s, body: a } = i, u = s.style.getPropertyValue("--scrollbar-width"), c = u ? Number.parseInt(u, 10) : o.innerWidth - s.clientWidth, l = () => Du(
      s,
      "--scrollbar-width",
      `${c}px`
    ), f = ju(s), m = () => ar(a, {
      overflow: "hidden",
      [f]: `${c}px`
    }), h = () => {
      var d, w;
      const { scrollX: x, scrollY: v, visualViewport: b } = o, g = (d = b?.offsetLeft) != null ? d : 0, y = (w = b?.offsetTop) != null ? w : 0, E = ar(a, {
        position: "fixed",
        overflow: "hidden",
        top: `${-(v - Math.floor(y))}px`,
        left: `${-(x - Math.floor(g))}px`,
        right: "0",
        [f]: `${c}px`
      });
      return () => {
        E(), process.env.NODE_ENV !== "test" && o.scrollTo({ left: x, top: v, behavior: "instant" });
      };
    }, p = Er() && !Ic();
    return ht(
      l(),
      p ? h() : m()
    );
  }, [r, t]);
}
function Gu(t, ...e) {
  if (!t) return !1;
  const n = t.getAttribute("data-focus-trap");
  return n == null ? !1 : e.length ? n === "" ? !1 : e.some((r) => n === r) : !0;
}
function bs() {
  return "inert" in HTMLElement.prototype;
}
function Uu(t) {
  return $r(t, "aria-hidden", "true");
}
function ws(t, e) {
  if (!("style" in t)) return fn;
  if (bs())
    return se(t, "inert", !0);
  const r = Cn(t, !0).map((i) => {
    if (e?.some((s) => s && Z(s, i))) return fn;
    const o = Ye(i, "focus", () => (i.focus = fn, () => {
      delete i.focus;
    }));
    return ht($r(i, "tabindex", "-1"), o);
  });
  return ht(
    ...r,
    Uu(t),
    ar(t, {
      pointerEvents: "none",
      userSelect: "none",
      cursor: "default"
    })
  );
}
function Xu(t, e) {
  const n = [], r = e.map((o) => o?.id);
  return Br(
    t,
    e,
    (o) => {
      gs(o, ...r) || Gu(o, ...r) || n.unshift(ws(o, e));
    },
    (o) => {
      o.hasAttribute("role") && (e.some((s) => s && Z(s, o)) || n.unshift($r(o, "role", "none")));
    }
  ), () => {
    for (const o of n)
      o();
  };
}
var Yu = "div", Ku = [
  "a",
  "button",
  "details",
  "dialog",
  "div",
  "form",
  "h1",
  "h2",
  "h3",
  "h4",
  "h5",
  "h6",
  "header",
  "img",
  "input",
  "label",
  "li",
  "nav",
  "ol",
  "p",
  "section",
  "select",
  "span",
  "summary",
  "textarea",
  "ul",
  "svg"
];
gt(
  function(e) {
    return e;
  }
);
var yn = st(function(e) {
  return ut(Yu, e);
});
Object.assign(
  yn,
  Ku.reduce((t, e) => (t[e] = st(function(r) {
    return ut(e, r);
  }), t), {})
);
function Zu({
  store: t,
  backdrop: e,
  alwaysVisible: n,
  hidden: r
}) {
  const i = F(null), o = vu({ disclosure: t }), s = $(t, "contentElement");
  V(() => {
    const c = i.current, l = s;
    c && l && (c.style.zIndex = getComputedStyle(l).zIndex);
  }, [s]), U(() => {
    const c = s?.id;
    if (!c) return;
    const l = i.current;
    if (l)
      return ys(l, c);
  }, [s]);
  const a = Lr({
    ref: i,
    store: o,
    role: "presentation",
    "data-backdrop": s?.id || "",
    alwaysVisible: n,
    hidden: r ?? void 0,
    style: {
      position: "fixed",
      top: 0,
      right: 0,
      bottom: 0,
      left: 0
    }
  });
  return e ? Fe(e) ? /* @__PURE__ */ C(yn, { ...a, render: e }) : /* @__PURE__ */ C(yn, { ...a, render: /* @__PURE__ */ C(typeof e != "boolean" ? e : "div", {}) }) : null;
}
function xs(t = {}) {
  return cs(t);
}
function Ss(t, e, n) {
  return us(t, e, n);
}
function qu(t = {}) {
  const [e, n] = Dr(xs, t);
  return Ss(e, n, t);
}
var Ju = "div", Ui = Xo();
function Qu(t) {
  const e = Qt(t);
  return !e || t && Z(t, e) ? !1 : !!It(e);
}
function Xi(t, e = !1) {
  if (!t) return null;
  const n = "current" in t ? t.current : t;
  return n ? e ? It(n) ? n : null : n : null;
}
var Es = gt(function({
  store: e,
  open: n,
  onClose: r,
  focusable: i = !0,
  modal: o = !0,
  portal: s = o,
  backdrop: a = o,
  hideOnEscape: u = !0,
  hideOnInteractOutside: c = !0,
  getPersistentElements: l,
  preventBodyScroll: f = o,
  autoFocusOnShow: m = !0,
  autoFocusOnHide: h = !0,
  initialFocus: p,
  finalFocus: d,
  unmountOnHide: w,
  unstable_treeSnapshotKey: x,
  ...v
}) {
  const b = Mr(), g = F(null), y = qu({
    store: e || b,
    open: n,
    setOpen(R) {
      if (R) return;
      const G = g.current;
      if (!G) return;
      const X = new Event("close", { bubbles: !1, cancelable: !0 });
      r && G.addEventListener("close", r, { once: !0 }), G.dispatchEvent(X), X.defaultPrevented && y.setOpen(!0);
    }
  }), { portalRef: E, domReady: S } = Ar(s, v.portalRef), A = v.preserveTabOrder, _ = $(
    y,
    (R) => A && !o && R.mounted
  ), T = Zo(v.id), O = $(y, "open"), M = $(y, "mounted"), D = $(y, "contentElement"), I = as(M, v.hidden, v.alwaysVisible);
  zu(D, T, f && !I);
  const k = F(!1);
  U(() => Ht(y, ["open"], (R) => {
    R.open && (k.current = !1);
  }), [y]), Bu(
    y,
    c,
    S,
    k
  );
  const { wrapElement: P, nestedDialogs: N } = Hu(y);
  v = xt(v, P, [P]);
  const L = F(null);
  Ui && V(() => {
    if (!S) return;
    const R = g.current;
    if (!R) return;
    const G = K(R), X = (j) => {
      L.current = j.target;
    };
    return G.addEventListener("mousedown", X, !0), () => {
      G.removeEventListener("mousedown", X, !0);
    };
  }, [S]), U(() => {
    if (!O) return;
    const R = g.current, G = Qt(R, !0);
    if (G) {
      if (G.tagName === "BODY") {
        const X = L.current;
        if (L.current = null, !X?.isConnected || !It(X) || R && Z(R, X)) return;
        y.setDisclosureElement(X);
        return;
      }
      R && Z(R, G) || y.setDisclosureElement(G);
    }
  }, [y, O]), V(() => {
    if (!M || !S) return;
    const R = g.current;
    if (!R) return;
    const G = Ve(R), X = G.visualViewport || G, j = () => {
      var ft, bt;
      const fe = (bt = (ft = G.visualViewport) == null ? void 0 : ft.height) != null ? bt : G.innerHeight;
      R.style.setProperty("--dialog-viewport-height", `${fe}px`);
    };
    return j(), X.addEventListener("resize", j), () => {
      X.removeEventListener("resize", j);
    };
  }, [M, S]), V(() => {
    if (!o || !M || !S) return;
    const R = g.current;
    if (!(!R || R.querySelector("[data-dialog-dismiss]")))
      return Tu(R, y.hide);
  }, [y, o, M, S]), U(() => {
    if (!bs() || O || !M || !S) return;
    const R = g.current;
    if (R)
      return ws(R);
  }, [O, M, S]);
  const z = O && S;
  U(() => {
    if (!T || !z) return;
    const R = g.current;
    return Fu(T, [R]);
  }, [T, z, x]);
  const at = Q(l);
  U(() => {
    if (!T || !z) return;
    const { disclosureElement: R } = y.getState(), G = g.current, X = at() || [], j = [
      G,
      ...X,
      ...N.map((ft) => ft.getState().contentElement)
    ];
    return o ? ht(
      zi(T, j),
      Xu(T, j)
    ) : zi(T, [R, ...j]);
  }, [
    T,
    y,
    z,
    at,
    N,
    o,
    x
  ]);
  const yt = !!m, tt = he(m), [_t, Oe] = Y(!1);
  V(() => {
    if (!O || !yt || !S || !D?.isConnected) return;
    const R = Xi(p, !0) || // If no initial focus is specified, we try to focus the first element
    // with the autofocus attribute. If it's an Ariakit component, the
    // Focusable component will consume the autoFocus prop and add the
    // data-autofocus attribute to the element instead.
    D.querySelector(
      "[data-autofocus=true],[autofocus]"
    ) || // We have to fallback to the first focusable element otherwise portaled
    // dialogs with preserveTabOrder set to true will not receive focus
    // properly because the elements aren't tabbable until the dialog receives
    // focus.
    Bc(D, !0, s && _) || // Finally, we fallback to the dialog element itself.
    D, G = It(R);
    tt(G ? R : null) && (Oe(!0), queueMicrotask(() => {
      y.getState().open && (R.focus(), Ui && G && R.scrollIntoView({ block: "nearest", inline: "nearest" }));
    }));
  }, [
    O,
    yt,
    S,
    D,
    p,
    s,
    _,
    y,
    tt
  ]);
  const Gt = !!h, wt = he(h), [Ut, lt] = Y(!1);
  U(() => {
    if (O)
      return lt(!0), () => lt(!1);
  }, [O]);
  const ee = At(
    (R, G = !0) => {
      if (k.current) return;
      const { disclosureElement: X } = y.getState();
      if (Qu(R)) return;
      let j = Xi(d) || X;
      if (j?.id) {
        const bt = K(j), fe = `[aria-activedescendant="${j.id}"]`, tn = bt.querySelector(fe);
        tn && (j = tn);
      }
      if (j && !It(j)) {
        const bt = j.closest("[data-dialog]");
        if (bt?.id) {
          const fe = K(bt), tn = `[aria-controls~="${bt.id}"]`, ii = fe.querySelector(tn);
          ii && (j = ii);
        }
      }
      const ft = j && It(j);
      if (!ft && G) {
        requestAnimationFrame(() => ee(R, !1));
        return;
      }
      wt(ft ? j : null) && ft && j?.focus();
    },
    [y, d, wt]
  ), _e = F(!1);
  U(() => {
    if (O || !Ut || !Gt) return;
    const R = g.current;
    _e.current = !0, ee(R);
  }, [O, Ut, S, Gt, ee]), V(() => {
    if (!Ut || !Gt) return;
    const R = g.current;
    return () => {
      if (_e.current) {
        _e.current = !1;
        return;
      }
      ee(R);
    };
  }, [Ut, Gt, ee]);
  const ne = he(u);
  V(() => {
    if (!S || !M) return;
    const R = (X) => {
      if (X.key !== "Escape" || X.defaultPrevented) return;
      const j = g.current;
      if (!j || Hr(j)) return;
      const ft = X.target;
      if (!ft) return;
      const { disclosureElement: bt } = y.getState();
      !!(ft.tagName === "BODY" || Z(j, ft) || !bt || Z(bt, ft)) && ne(X) && y.hide();
    }, G = D ? Ve(D) : void 0;
    return it("keydown", R, !0, G);
  }, [y, S, M, D, ne]), v = xt(
    v,
    (R) => /* @__PURE__ */ C(Ou, { level: o ? 1 : void 0, children: R }),
    [o]
  );
  const re = v.hidden, Te = v.alwaysVisible;
  v = xt(
    v,
    (R) => a ? /* @__PURE__ */ Et(St, { children: [
      /* @__PURE__ */ C(
        Zu,
        {
          store: y,
          backdrop: a,
          hidden: re,
          alwaysVisible: Te
        }
      ),
      R
    ] }) : R,
    [y, a, re, Te]
  );
  const [ue, le] = Y(), [Je, Qe] = Y();
  return v = xt(
    v,
    (R) => /* @__PURE__ */ C(Fr, { value: y, children: /* @__PURE__ */ C(fu.Provider, { value: le, children: /* @__PURE__ */ C(du.Provider, { value: Qe, children: R }) }) }),
    [y]
  ), v = {
    "data-dialog": "",
    role: "dialog",
    tabIndex: i ? -1 : void 0,
    "aria-labelledby": v["aria-label"] != null ? void 0 : ue,
    "aria-describedby": Je,
    ...v,
    id: T,
    ref: te(g, v.ref)
  }, v = vs({
    ...v,
    autoFocusOnShow: _t
  }), v = Lr({ store: y, ...v }), v = Tr({ ...v, focusable: i }), v = hs({ portal: s, ...v, portalRef: E, preserveTabOrder: _ }), v;
});
function On(t, e = Mr) {
  return st(function(r) {
    const i = e(), o = r.store || i;
    return $(
      o,
      (a) => !r.unmountOnHide || a?.mounted || !!r.open
    ) ? /* @__PURE__ */ C(t, { ...r }) : null;
  });
}
On(
  st(function(e) {
    const n = Es(e);
    return ut(Ju, n);
  }),
  Mr
);
const Kt = Math.min, dt = Math.max, bn = Math.round, cn = Math.floor, Ft = (t) => ({
  x: t,
  y: t
}), tl = {
  left: "right",
  right: "left",
  bottom: "top",
  top: "bottom"
};
function ur(t, e, n) {
  return dt(t, Kt(e, n));
}
function Zt(t, e) {
  return typeof t == "function" ? t(e) : t;
}
function Wt(t) {
  return t.split("-")[0];
}
function Ae(t) {
  return t.split("-")[1];
}
function Wr(t) {
  return t === "x" ? "y" : "x";
}
function jr(t) {
  return t === "y" ? "height" : "width";
}
function Nt(t) {
  const e = t[0];
  return e === "t" || e === "b" ? "y" : "x";
}
function zr(t) {
  return Wr(Nt(t));
}
function el(t, e, n) {
  n === void 0 && (n = !1);
  const r = Ae(t), i = zr(t), o = jr(i);
  let s = i === "x" ? r === (n ? "end" : "start") ? "right" : "left" : r === "start" ? "bottom" : "top";
  return e.reference[o] > e.floating[o] && (s = wn(s)), [s, wn(s)];
}
function nl(t) {
  const e = wn(t);
  return [lr(t), e, lr(e)];
}
function lr(t) {
  return t.includes("start") ? t.replace("start", "end") : t.replace("end", "start");
}
const Yi = ["left", "right"], Ki = ["right", "left"], rl = ["top", "bottom"], il = ["bottom", "top"];
function ol(t, e, n) {
  switch (t) {
    case "top":
    case "bottom":
      return n ? e ? Ki : Yi : e ? Yi : Ki;
    case "left":
    case "right":
      return e ? rl : il;
    default:
      return [];
  }
}
function sl(t, e, n, r) {
  const i = Ae(t);
  let o = ol(Wt(t), n === "start", r);
  return i && (o = o.map((s) => s + "-" + i), e && (o = o.concat(o.map(lr)))), o;
}
function wn(t) {
  const e = Wt(t);
  return tl[e] + t.slice(e.length);
}
function al(t) {
  return {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    ...t
  };
}
function As(t) {
  return typeof t != "number" ? al(t) : {
    top: t,
    right: t,
    bottom: t,
    left: t
  };
}
function xn(t) {
  const {
    x: e,
    y: n,
    width: r,
    height: i
  } = t;
  return {
    width: r,
    height: i,
    top: n,
    left: e,
    right: e + r,
    bottom: n + i,
    x: e,
    y: n
  };
}
function Zi(t, e, n) {
  let {
    reference: r,
    floating: i
  } = t;
  const o = Nt(e), s = zr(e), a = jr(s), u = Wt(e), c = o === "y", l = r.x + r.width / 2 - i.width / 2, f = r.y + r.height / 2 - i.height / 2, m = r[a] / 2 - i[a] / 2;
  let h;
  switch (u) {
    case "top":
      h = {
        x: l,
        y: r.y - i.height
      };
      break;
    case "bottom":
      h = {
        x: l,
        y: r.y + r.height
      };
      break;
    case "right":
      h = {
        x: r.x + r.width,
        y: f
      };
      break;
    case "left":
      h = {
        x: r.x - i.width,
        y: f
      };
      break;
    default:
      h = {
        x: r.x,
        y: r.y
      };
  }
  switch (Ae(e)) {
    case "start":
      h[s] -= m * (n && c ? -1 : 1);
      break;
    case "end":
      h[s] += m * (n && c ? -1 : 1);
      break;
  }
  return h;
}
async function cl(t, e) {
  var n;
  e === void 0 && (e = {});
  const {
    x: r,
    y: i,
    platform: o,
    rects: s,
    elements: a,
    strategy: u
  } = t, {
    boundary: c = "clippingAncestors",
    rootBoundary: l = "viewport",
    elementContext: f = "floating",
    altBoundary: m = !1,
    padding: h = 0
  } = Zt(e, t), p = As(h), w = a[m ? f === "floating" ? "reference" : "floating" : f], x = xn(await o.getClippingRect({
    element: (n = await (o.isElement == null ? void 0 : o.isElement(w))) == null || n ? w : w.contextElement || await (o.getDocumentElement == null ? void 0 : o.getDocumentElement(a.floating)),
    boundary: c,
    rootBoundary: l,
    strategy: u
  })), v = f === "floating" ? {
    x: r,
    y: i,
    width: s.floating.width,
    height: s.floating.height
  } : s.reference, b = await (o.getOffsetParent == null ? void 0 : o.getOffsetParent(a.floating)), g = await (o.isElement == null ? void 0 : o.isElement(b)) ? await (o.getScale == null ? void 0 : o.getScale(b)) || {
    x: 1,
    y: 1
  } : {
    x: 1,
    y: 1
  }, y = xn(o.convertOffsetParentRelativeRectToViewportRelativeRect ? await o.convertOffsetParentRelativeRectToViewportRelativeRect({
    elements: a,
    rect: v,
    offsetParent: b,
    strategy: u
  }) : v);
  return {
    top: (x.top - y.top + p.top) / g.y,
    bottom: (y.bottom - x.bottom + p.bottom) / g.y,
    left: (x.left - y.left + p.left) / g.x,
    right: (y.right - x.right + p.right) / g.x
  };
}
const ul = 50, ll = async (t, e, n) => {
  const {
    placement: r = "bottom",
    strategy: i = "absolute",
    middleware: o = [],
    platform: s
  } = n, a = s.detectOverflow ? s : {
    ...s,
    detectOverflow: cl
  }, u = await (s.isRTL == null ? void 0 : s.isRTL(e));
  let c = await s.getElementRects({
    reference: t,
    floating: e,
    strategy: i
  }), {
    x: l,
    y: f
  } = Zi(c, r, u), m = r, h = 0;
  const p = {};
  for (let d = 0; d < o.length; d++) {
    const w = o[d];
    if (!w)
      continue;
    const {
      name: x,
      fn: v
    } = w, {
      x: b,
      y: g,
      data: y,
      reset: E
    } = await v({
      x: l,
      y: f,
      initialPlacement: r,
      placement: m,
      strategy: i,
      middlewareData: p,
      rects: c,
      platform: a,
      elements: {
        reference: t,
        floating: e
      }
    });
    l = b ?? l, f = g ?? f, p[x] = {
      ...p[x],
      ...y
    }, E && h < ul && (h++, typeof E == "object" && (E.placement && (m = E.placement), E.rects && (c = E.rects === !0 ? await s.getElementRects({
      reference: t,
      floating: e,
      strategy: i
    }) : E.rects), {
      x: l,
      y: f
    } = Zi(c, m, u)), d = -1);
  }
  return {
    x: l,
    y: f,
    placement: m,
    strategy: i,
    middlewareData: p
  };
}, fl = (t) => ({
  name: "arrow",
  options: t,
  async fn(e) {
    const {
      x: n,
      y: r,
      placement: i,
      rects: o,
      platform: s,
      elements: a,
      middlewareData: u
    } = e, {
      element: c,
      padding: l = 0
    } = Zt(t, e) || {};
    if (c == null)
      return {};
    const f = As(l), m = {
      x: n,
      y: r
    }, h = zr(i), p = jr(h), d = await s.getDimensions(c), w = h === "y", x = w ? "top" : "left", v = w ? "bottom" : "right", b = w ? "clientHeight" : "clientWidth", g = o.reference[p] + o.reference[h] - m[h] - o.floating[p], y = m[h] - o.reference[h], E = await (s.getOffsetParent == null ? void 0 : s.getOffsetParent(c));
    let S = E ? E[b] : 0;
    (!S || !await (s.isElement == null ? void 0 : s.isElement(E))) && (S = a.floating[b] || o.floating[p]);
    const A = g / 2 - y / 2, _ = S / 2 - d[p] / 2 - 1, T = Kt(f[x], _), O = Kt(f[v], _), M = T, D = S - d[p] - O, I = S / 2 - d[p] / 2 + A, k = ur(M, I, D), P = !u.arrow && Ae(i) != null && I !== k && o.reference[p] / 2 - (I < M ? T : O) - d[p] / 2 < 0, N = P ? I < M ? I - M : I - D : 0;
    return {
      [h]: m[h] + N,
      data: {
        [h]: k,
        centerOffset: I - k - N,
        ...P && {
          alignmentOffset: N
        }
      },
      reset: P
    };
  }
}), dl = function(t) {
  return t === void 0 && (t = {}), {
    name: "flip",
    options: t,
    async fn(e) {
      var n, r;
      const {
        placement: i,
        middlewareData: o,
        rects: s,
        initialPlacement: a,
        platform: u,
        elements: c
      } = e, {
        mainAxis: l = !0,
        crossAxis: f = !0,
        fallbackPlacements: m,
        fallbackStrategy: h = "bestFit",
        fallbackAxisSideDirection: p = "none",
        flipAlignment: d = !0,
        ...w
      } = Zt(t, e);
      if ((n = o.arrow) != null && n.alignmentOffset)
        return {};
      const x = Wt(i), v = Nt(a), b = Wt(a) === a, g = await (u.isRTL == null ? void 0 : u.isRTL(c.floating)), y = m || (b || !d ? [wn(a)] : nl(a)), E = p !== "none";
      !m && E && y.push(...sl(a, d, p, g));
      const S = [a, ...y], A = await u.detectOverflow(e, w), _ = [];
      let T = ((r = o.flip) == null ? void 0 : r.overflows) || [];
      if (l && _.push(A[x]), f) {
        const I = el(i, s, g);
        _.push(A[I[0]], A[I[1]]);
      }
      if (T = [...T, {
        placement: i,
        overflows: _
      }], !_.every((I) => I <= 0)) {
        var O, M;
        const I = (((O = o.flip) == null ? void 0 : O.index) || 0) + 1, k = S[I];
        if (k && (!(f === "alignment" ? v !== Nt(k) : !1) || // We leave the current main axis only if every placement on that axis
        // overflows the main axis.
        T.every((L) => Nt(L.placement) === v ? L.overflows[0] > 0 : !0)))
          return {
            data: {
              index: I,
              overflows: T
            },
            reset: {
              placement: k
            }
          };
        let P = (M = T.filter((N) => N.overflows[0] <= 0).sort((N, L) => N.overflows[1] - L.overflows[1])[0]) == null ? void 0 : M.placement;
        if (!P)
          switch (h) {
            case "bestFit": {
              var D;
              const N = (D = T.filter((L) => {
                if (E) {
                  const z = Nt(L.placement);
                  return z === v || // Create a bias to the `y` side axis due to horizontal
                  // reading directions favoring greater width.
                  z === "y";
                }
                return !0;
              }).map((L) => [L.placement, L.overflows.filter((z) => z > 0).reduce((z, at) => z + at, 0)]).sort((L, z) => L[1] - z[1])[0]) == null ? void 0 : D[0];
              N && (P = N);
              break;
            }
            case "initialPlacement":
              P = a;
              break;
          }
        if (i !== P)
          return {
            reset: {
              placement: P
            }
          };
      }
      return {};
    }
  };
}, Ps = /* @__PURE__ */ new Set(["left", "top"]);
async function pl(t, e) {
  const {
    placement: n,
    platform: r,
    elements: i
  } = t, o = await (r.isRTL == null ? void 0 : r.isRTL(i.floating)), s = Wt(n), a = Ae(n), u = Nt(n) === "y", c = Ps.has(s) ? -1 : 1, l = o && u ? -1 : 1, f = Zt(e, t);
  let {
    mainAxis: m,
    crossAxis: h,
    alignmentAxis: p
  } = typeof f == "number" ? {
    mainAxis: f,
    crossAxis: 0,
    alignmentAxis: null
  } : {
    mainAxis: f.mainAxis || 0,
    crossAxis: f.crossAxis || 0,
    alignmentAxis: f.alignmentAxis
  };
  return a && typeof p == "number" && (h = a === "end" ? p * -1 : p), u ? {
    x: h * l,
    y: m * c
  } : {
    x: m * c,
    y: h * l
  };
}
const ml = function(t) {
  return t === void 0 && (t = 0), {
    name: "offset",
    options: t,
    async fn(e) {
      var n, r;
      const {
        x: i,
        y: o,
        placement: s,
        middlewareData: a
      } = e, u = await pl(e, t);
      return s === ((n = a.offset) == null ? void 0 : n.placement) && (r = a.arrow) != null && r.alignmentOffset ? {} : {
        x: i + u.x,
        y: o + u.y,
        data: {
          ...u,
          placement: s
        }
      };
    }
  };
}, hl = function(t) {
  return t === void 0 && (t = {}), {
    name: "shift",
    options: t,
    async fn(e) {
      const {
        x: n,
        y: r,
        placement: i,
        platform: o
      } = e, {
        mainAxis: s = !0,
        crossAxis: a = !1,
        limiter: u = {
          fn: (x) => {
            let {
              x: v,
              y: b
            } = x;
            return {
              x: v,
              y: b
            };
          }
        },
        ...c
      } = Zt(t, e), l = {
        x: n,
        y: r
      }, f = await o.detectOverflow(e, c), m = Nt(Wt(i)), h = Wr(m);
      let p = l[h], d = l[m];
      if (s) {
        const x = h === "y" ? "top" : "left", v = h === "y" ? "bottom" : "right", b = p + f[x], g = p - f[v];
        p = ur(b, p, g);
      }
      if (a) {
        const x = m === "y" ? "top" : "left", v = m === "y" ? "bottom" : "right", b = d + f[x], g = d - f[v];
        d = ur(b, d, g);
      }
      const w = u.fn({
        ...e,
        [h]: p,
        [m]: d
      });
      return {
        ...w,
        data: {
          x: w.x - n,
          y: w.y - r,
          enabled: {
            [h]: s,
            [m]: a
          }
        }
      };
    }
  };
}, vl = function(t) {
  return t === void 0 && (t = {}), {
    options: t,
    fn(e) {
      const {
        x: n,
        y: r,
        placement: i,
        rects: o,
        middlewareData: s
      } = e, {
        offset: a = 0,
        mainAxis: u = !0,
        crossAxis: c = !0
      } = Zt(t, e), l = {
        x: n,
        y: r
      }, f = Nt(i), m = Wr(f);
      let h = l[m], p = l[f];
      const d = Zt(a, e), w = typeof d == "number" ? {
        mainAxis: d,
        crossAxis: 0
      } : {
        mainAxis: 0,
        crossAxis: 0,
        ...d
      };
      if (u) {
        const b = m === "y" ? "height" : "width", g = o.reference[m] - o.floating[b] + w.mainAxis, y = o.reference[m] + o.reference[b] - w.mainAxis;
        h < g ? h = g : h > y && (h = y);
      }
      if (c) {
        var x, v;
        const b = m === "y" ? "width" : "height", g = Ps.has(Wt(i)), y = o.reference[f] - o.floating[b] + (g && ((x = s.offset) == null ? void 0 : x[f]) || 0) + (g ? 0 : w.crossAxis), E = o.reference[f] + o.reference[b] + (g ? 0 : ((v = s.offset) == null ? void 0 : v[f]) || 0) - (g ? w.crossAxis : 0);
        p < y ? p = y : p > E && (p = E);
      }
      return {
        [m]: h,
        [f]: p
      };
    }
  };
}, gl = function(t) {
  return t === void 0 && (t = {}), {
    name: "size",
    options: t,
    async fn(e) {
      var n, r;
      const {
        placement: i,
        rects: o,
        platform: s,
        elements: a
      } = e, {
        apply: u = () => {
        },
        ...c
      } = Zt(t, e), l = await s.detectOverflow(e, c), f = Wt(i), m = Ae(i), h = Nt(i) === "y", {
        width: p,
        height: d
      } = o.floating;
      let w, x;
      f === "top" || f === "bottom" ? (w = f, x = m === (await (s.isRTL == null ? void 0 : s.isRTL(a.floating)) ? "start" : "end") ? "left" : "right") : (x = f, w = m === "end" ? "top" : "bottom");
      const v = d - l.top - l.bottom, b = p - l.left - l.right, g = Kt(d - l[w], v), y = Kt(p - l[x], b), E = !e.middlewareData.shift;
      let S = g, A = y;
      if ((n = e.middlewareData.shift) != null && n.enabled.x && (A = b), (r = e.middlewareData.shift) != null && r.enabled.y && (S = v), E && !m) {
        const T = dt(l.left, 0), O = dt(l.right, 0), M = dt(l.top, 0), D = dt(l.bottom, 0);
        h ? A = p - 2 * (T !== 0 || O !== 0 ? T + O : dt(l.left, l.right)) : S = d - 2 * (M !== 0 || D !== 0 ? M + D : dt(l.top, l.bottom));
      }
      await u({
        ...e,
        availableWidth: A,
        availableHeight: S
      });
      const _ = await s.getDimensions(a.floating);
      return p !== _.width || d !== _.height ? {
        reset: {
          rects: !0
        }
      } : {};
    }
  };
};
function _n() {
  return typeof window < "u";
}
function Pe(t) {
  return Cs(t) ? (t.nodeName || "").toLowerCase() : "#document";
}
function pt(t) {
  var e;
  return (t == null || (e = t.ownerDocument) == null ? void 0 : e.defaultView) || window;
}
function Vt(t) {
  var e;
  return (e = (Cs(t) ? t.ownerDocument : t.document) || window.document) == null ? void 0 : e.documentElement;
}
function Cs(t) {
  return _n() ? t instanceof Node || t instanceof pt(t).Node : !1;
}
function Pt(t) {
  return _n() ? t instanceof Element || t instanceof pt(t).Element : !1;
}
function zt(t) {
  return _n() ? t instanceof HTMLElement || t instanceof pt(t).HTMLElement : !1;
}
function qi(t) {
  return !_n() || typeof ShadowRoot > "u" ? !1 : t instanceof ShadowRoot || t instanceof pt(t).ShadowRoot;
}
function Ke(t) {
  const {
    overflow: e,
    overflowX: n,
    overflowY: r,
    display: i
  } = Ct(t);
  return /auto|scroll|overlay|hidden|clip/.test(e + r + n) && i !== "inline" && i !== "contents";
}
function yl(t) {
  return /^(table|td|th)$/.test(Pe(t));
}
function Tn(t) {
  try {
    if (t.matches(":popover-open"))
      return !0;
  } catch {
  }
  try {
    return t.matches(":modal");
  } catch {
    return !1;
  }
}
const bl = /transform|translate|scale|rotate|perspective|filter/, wl = /paint|layout|strict|content/, ie = (t) => !!t && t !== "none";
let Yn;
function Gr(t) {
  const e = Pt(t) ? Ct(t) : t;
  return ie(e.transform) || ie(e.translate) || ie(e.scale) || ie(e.rotate) || ie(e.perspective) || !Ur() && (ie(e.backdropFilter) || ie(e.filter)) || bl.test(e.willChange || "") || wl.test(e.contain || "");
}
function xl(t) {
  let e = qt(t);
  for (; zt(e) && !be(e); ) {
    if (Gr(e))
      return e;
    if (Tn(e))
      return null;
    e = qt(e);
  }
  return null;
}
function Ur() {
  return Yn == null && (Yn = typeof CSS < "u" && CSS.supports && CSS.supports("-webkit-backdrop-filter", "none")), Yn;
}
function be(t) {
  return /^(html|body|#document)$/.test(Pe(t));
}
function Ct(t) {
  return pt(t).getComputedStyle(t);
}
function Rn(t) {
  return Pt(t) ? {
    scrollLeft: t.scrollLeft,
    scrollTop: t.scrollTop
  } : {
    scrollLeft: t.scrollX,
    scrollTop: t.scrollY
  };
}
function qt(t) {
  if (Pe(t) === "html")
    return t;
  const e = (
    // Step into the shadow DOM of the parent of a slotted node.
    t.assignedSlot || // DOM Element detected.
    t.parentNode || // ShadowRoot detected.
    qi(t) && t.host || // Fallback.
    Vt(t)
  );
  return qi(e) ? e.host : e;
}
function Os(t) {
  const e = qt(t);
  return be(e) ? t.ownerDocument ? t.ownerDocument.body : t.body : zt(e) && Ke(e) ? e : Os(e);
}
function Be(t, e, n) {
  var r;
  e === void 0 && (e = []), n === void 0 && (n = !0);
  const i = Os(t), o = i === ((r = t.ownerDocument) == null ? void 0 : r.body), s = pt(i);
  if (o) {
    const a = fr(s);
    return e.concat(s, s.visualViewport || [], Ke(i) ? i : [], a && n ? Be(a) : []);
  } else
    return e.concat(i, Be(i, [], n));
}
function fr(t) {
  return t.parent && Object.getPrototypeOf(t.parent) ? t.frameElement : null;
}
function _s(t) {
  const e = Ct(t);
  let n = parseFloat(e.width) || 0, r = parseFloat(e.height) || 0;
  const i = zt(t), o = i ? t.offsetWidth : n, s = i ? t.offsetHeight : r, a = bn(n) !== o || bn(r) !== s;
  return a && (n = o, r = s), {
    width: n,
    height: r,
    $: a
  };
}
function Xr(t) {
  return Pt(t) ? t : t.contextElement;
}
function ve(t) {
  const e = Xr(t);
  if (!zt(e))
    return Ft(1);
  const n = e.getBoundingClientRect(), {
    width: r,
    height: i,
    $: o
  } = _s(e);
  let s = (o ? bn(n.width) : n.width) / r, a = (o ? bn(n.height) : n.height) / i;
  return (!s || !Number.isFinite(s)) && (s = 1), (!a || !Number.isFinite(a)) && (a = 1), {
    x: s,
    y: a
  };
}
const Sl = /* @__PURE__ */ Ft(0);
function Ts(t) {
  const e = pt(t);
  return !Ur() || !e.visualViewport ? Sl : {
    x: e.visualViewport.offsetLeft,
    y: e.visualViewport.offsetTop
  };
}
function El(t, e, n) {
  return e === void 0 && (e = !1), !n || e && n !== pt(t) ? !1 : e;
}
function ae(t, e, n, r) {
  e === void 0 && (e = !1), n === void 0 && (n = !1);
  const i = t.getBoundingClientRect(), o = Xr(t);
  let s = Ft(1);
  e && (r ? Pt(r) && (s = ve(r)) : s = ve(t));
  const a = El(o, n, r) ? Ts(o) : Ft(0);
  let u = (i.left + a.x) / s.x, c = (i.top + a.y) / s.y, l = i.width / s.x, f = i.height / s.y;
  if (o) {
    const m = pt(o), h = r && Pt(r) ? pt(r) : r;
    let p = m, d = fr(p);
    for (; d && r && h !== p; ) {
      const w = ve(d), x = d.getBoundingClientRect(), v = Ct(d), b = x.left + (d.clientLeft + parseFloat(v.paddingLeft)) * w.x, g = x.top + (d.clientTop + parseFloat(v.paddingTop)) * w.y;
      u *= w.x, c *= w.y, l *= w.x, f *= w.y, u += b, c += g, p = pt(d), d = fr(p);
    }
  }
  return xn({
    width: l,
    height: f,
    x: u,
    y: c
  });
}
function Dn(t, e) {
  const n = Rn(t).scrollLeft;
  return e ? e.left + n : ae(Vt(t)).left + n;
}
function Rs(t, e) {
  const n = t.getBoundingClientRect(), r = n.left + e.scrollLeft - Dn(t, n), i = n.top + e.scrollTop;
  return {
    x: r,
    y: i
  };
}
function Al(t) {
  let {
    elements: e,
    rect: n,
    offsetParent: r,
    strategy: i
  } = t;
  const o = i === "fixed", s = Vt(r), a = e ? Tn(e.floating) : !1;
  if (r === s || a && o)
    return n;
  let u = {
    scrollLeft: 0,
    scrollTop: 0
  }, c = Ft(1);
  const l = Ft(0), f = zt(r);
  if ((f || !f && !o) && ((Pe(r) !== "body" || Ke(s)) && (u = Rn(r)), f)) {
    const h = ae(r);
    c = ve(r), l.x = h.x + r.clientLeft, l.y = h.y + r.clientTop;
  }
  const m = s && !f && !o ? Rs(s, u) : Ft(0);
  return {
    width: n.width * c.x,
    height: n.height * c.y,
    x: n.x * c.x - u.scrollLeft * c.x + l.x + m.x,
    y: n.y * c.y - u.scrollTop * c.y + l.y + m.y
  };
}
function Pl(t) {
  return Array.from(t.getClientRects());
}
function Cl(t) {
  const e = Vt(t), n = Rn(t), r = t.ownerDocument.body, i = dt(e.scrollWidth, e.clientWidth, r.scrollWidth, r.clientWidth), o = dt(e.scrollHeight, e.clientHeight, r.scrollHeight, r.clientHeight);
  let s = -n.scrollLeft + Dn(t);
  const a = -n.scrollTop;
  return Ct(r).direction === "rtl" && (s += dt(e.clientWidth, r.clientWidth) - i), {
    width: i,
    height: o,
    x: s,
    y: a
  };
}
const Ji = 25;
function Ol(t, e) {
  const n = pt(t), r = Vt(t), i = n.visualViewport;
  let o = r.clientWidth, s = r.clientHeight, a = 0, u = 0;
  if (i) {
    o = i.width, s = i.height;
    const l = Ur();
    (!l || l && e === "fixed") && (a = i.offsetLeft, u = i.offsetTop);
  }
  const c = Dn(r);
  if (c <= 0) {
    const l = r.ownerDocument, f = l.body, m = getComputedStyle(f), h = l.compatMode === "CSS1Compat" && parseFloat(m.marginLeft) + parseFloat(m.marginRight) || 0, p = Math.abs(r.clientWidth - f.clientWidth - h);
    p <= Ji && (o -= p);
  } else c <= Ji && (o += c);
  return {
    width: o,
    height: s,
    x: a,
    y: u
  };
}
function _l(t, e) {
  const n = ae(t, !0, e === "fixed"), r = n.top + t.clientTop, i = n.left + t.clientLeft, o = zt(t) ? ve(t) : Ft(1), s = t.clientWidth * o.x, a = t.clientHeight * o.y, u = i * o.x, c = r * o.y;
  return {
    width: s,
    height: a,
    x: u,
    y: c
  };
}
function Qi(t, e, n) {
  let r;
  if (e === "viewport")
    r = Ol(t, n);
  else if (e === "document")
    r = Cl(Vt(t));
  else if (Pt(e))
    r = _l(e, n);
  else {
    const i = Ts(t);
    r = {
      x: e.x - i.x,
      y: e.y - i.y,
      width: e.width,
      height: e.height
    };
  }
  return xn(r);
}
function Ds(t, e) {
  const n = qt(t);
  return n === e || !Pt(n) || be(n) ? !1 : Ct(n).position === "fixed" || Ds(n, e);
}
function Tl(t, e) {
  const n = e.get(t);
  if (n)
    return n;
  let r = Be(t, [], !1).filter((a) => Pt(a) && Pe(a) !== "body"), i = null;
  const o = Ct(t).position === "fixed";
  let s = o ? qt(t) : t;
  for (; Pt(s) && !be(s); ) {
    const a = Ct(s), u = Gr(s);
    !u && a.position === "fixed" && (i = null), (o ? !u && !i : !u && a.position === "static" && !!i && (i.position === "absolute" || i.position === "fixed") || Ke(s) && !u && Ds(t, s)) ? r = r.filter((l) => l !== s) : i = a, s = qt(s);
  }
  return e.set(t, r), r;
}
function Rl(t) {
  let {
    element: e,
    boundary: n,
    rootBoundary: r,
    strategy: i
  } = t;
  const s = [...n === "clippingAncestors" ? Tn(e) ? [] : Tl(e, this._c) : [].concat(n), r], a = Qi(e, s[0], i);
  let u = a.top, c = a.right, l = a.bottom, f = a.left;
  for (let m = 1; m < s.length; m++) {
    const h = Qi(e, s[m], i);
    u = dt(h.top, u), c = Kt(h.right, c), l = Kt(h.bottom, l), f = dt(h.left, f);
  }
  return {
    width: c - f,
    height: l - u,
    x: f,
    y: u
  };
}
function Dl(t) {
  const {
    width: e,
    height: n
  } = _s(t);
  return {
    width: e,
    height: n
  };
}
function Il(t, e, n) {
  const r = zt(e), i = Vt(e), o = n === "fixed", s = ae(t, !0, o, e);
  let a = {
    scrollLeft: 0,
    scrollTop: 0
  };
  const u = Ft(0);
  function c() {
    u.x = Dn(i);
  }
  if (r || !r && !o)
    if ((Pe(e) !== "body" || Ke(i)) && (a = Rn(e)), r) {
      const h = ae(e, !0, o, e);
      u.x = h.x + e.clientLeft, u.y = h.y + e.clientTop;
    } else i && c();
  o && !r && i && c();
  const l = i && !r && !o ? Rs(i, a) : Ft(0), f = s.left + a.scrollLeft - u.x - l.x, m = s.top + a.scrollTop - u.y - l.y;
  return {
    x: f,
    y: m,
    width: s.width,
    height: s.height
  };
}
function Kn(t) {
  return Ct(t).position === "static";
}
function to(t, e) {
  if (!zt(t) || Ct(t).position === "fixed")
    return null;
  if (e)
    return e(t);
  let n = t.offsetParent;
  return Vt(t) === n && (n = n.ownerDocument.body), n;
}
function Is(t, e) {
  const n = pt(t);
  if (Tn(t))
    return n;
  if (!zt(t)) {
    let i = qt(t);
    for (; i && !be(i); ) {
      if (Pt(i) && !Kn(i))
        return i;
      i = qt(i);
    }
    return n;
  }
  let r = to(t, e);
  for (; r && yl(r) && Kn(r); )
    r = to(r, e);
  return r && be(r) && Kn(r) && !Gr(r) ? n : r || xl(t) || n;
}
const Nl = async function(t) {
  const e = this.getOffsetParent || Is, n = this.getDimensions, r = await n(t.floating);
  return {
    reference: Il(t.reference, await e(t.floating), t.strategy),
    floating: {
      x: 0,
      y: 0,
      width: r.width,
      height: r.height
    }
  };
};
function Ml(t) {
  return Ct(t).direction === "rtl";
}
const Fl = {
  convertOffsetParentRelativeRectToViewportRelativeRect: Al,
  getDocumentElement: Vt,
  getClippingRect: Rl,
  getOffsetParent: Is,
  getElementRects: Nl,
  getClientRects: Pl,
  getDimensions: Dl,
  getScale: ve,
  isElement: Pt,
  isRTL: Ml
};
function Ns(t, e) {
  return t.x === e.x && t.y === e.y && t.width === e.width && t.height === e.height;
}
function Ll(t, e) {
  let n = null, r;
  const i = Vt(t);
  function o() {
    var a;
    clearTimeout(r), (a = n) == null || a.disconnect(), n = null;
  }
  function s(a, u) {
    a === void 0 && (a = !1), u === void 0 && (u = 1), o();
    const c = t.getBoundingClientRect(), {
      left: l,
      top: f,
      width: m,
      height: h
    } = c;
    if (a || e(), !m || !h)
      return;
    const p = cn(f), d = cn(i.clientWidth - (l + m)), w = cn(i.clientHeight - (f + h)), x = cn(l), b = {
      rootMargin: -p + "px " + -d + "px " + -w + "px " + -x + "px",
      threshold: dt(0, Kt(1, u)) || 1
    };
    let g = !0;
    function y(E) {
      const S = E[0].intersectionRatio;
      if (S !== u) {
        if (!g)
          return s();
        S ? s(!1, S) : r = setTimeout(() => {
          s(!1, 1e-7);
        }, 1e3);
      }
      S === 1 && !Ns(c, t.getBoundingClientRect()) && s(), g = !1;
    }
    try {
      n = new IntersectionObserver(y, {
        ...b,
        // Handle <iframe>s
        root: i.ownerDocument
      });
    } catch {
      n = new IntersectionObserver(y, b);
    }
    n.observe(t);
  }
  return s(!0), o;
}
function kl(t, e, n, r) {
  r === void 0 && (r = {});
  const {
    ancestorScroll: i = !0,
    ancestorResize: o = !0,
    elementResize: s = typeof ResizeObserver == "function",
    layoutShift: a = typeof IntersectionObserver == "function",
    animationFrame: u = !1
  } = r, c = Xr(t), l = i || o ? [...c ? Be(c) : [], ...e ? Be(e) : []] : [];
  l.forEach((x) => {
    i && x.addEventListener("scroll", n, {
      passive: !0
    }), o && x.addEventListener("resize", n);
  });
  const f = c && a ? Ll(c, n) : null;
  let m = -1, h = null;
  s && (h = new ResizeObserver((x) => {
    let [v] = x;
    v && v.target === c && h && e && (h.unobserve(e), cancelAnimationFrame(m), m = requestAnimationFrame(() => {
      var b;
      (b = h) == null || b.observe(e);
    })), n();
  }), c && !u && h.observe(c), e && h.observe(e));
  let p, d = u ? ae(t) : null;
  u && w();
  function w() {
    const x = ae(t);
    d && !Ns(d, x) && n(), d = x, p = requestAnimationFrame(w);
  }
  return n(), () => {
    var x;
    l.forEach((v) => {
      i && v.removeEventListener("scroll", n), o && v.removeEventListener("resize", n);
    }), f?.(), (x = h) == null || x.disconnect(), h = null, u && cancelAnimationFrame(p);
  };
}
const Vl = ml, $l = hl, Bl = dl, Hl = gl, Wl = fl, jl = vl, zl = (t, e, n) => {
  const r = /* @__PURE__ */ new Map(), i = {
    platform: Fl,
    ...n
  }, o = {
    ...i.platform,
    _c: r
  };
  return ll(t, e, {
    ...i,
    platform: o
  });
};
var Gl = "div";
function eo(t = 0, e = 0, n = 0, r = 0) {
  if (typeof DOMRect == "function")
    return new DOMRect(t, e, n, r);
  const i = {
    x: t,
    y: e,
    width: n,
    height: r,
    top: e,
    right: t + n,
    bottom: e + r,
    left: t
  };
  return { ...i, toJSON: () => i };
}
function Ul(t) {
  if (!t) return eo();
  const { x: e, y: n, width: r, height: i } = t;
  return eo(e, n, r, i);
}
function Xl(t, e) {
  return {
    contextElement: t || void 0,
    getBoundingClientRect: () => {
      const r = t, i = e?.(r);
      return i || !r ? Ul(i) : r.getBoundingClientRect();
    }
  };
}
function Yl(t) {
  return /^(?:top|bottom|left|right)(?:-(?:start|end))?$/.test(t);
}
function no(t) {
  const e = window.devicePixelRatio || 1;
  return Math.round(t * e) / e;
}
function Kl(t, e) {
  return Vl(({ placement: n }) => {
    var r;
    const i = (t?.clientHeight || 0) / 2, o = typeof e.gutter == "number" ? e.gutter + i : (r = e.gutter) != null ? r : i;
    return {
      crossAxis: !!n.split("-")[1] ? void 0 : e.shift,
      mainAxis: o,
      alignmentAxis: e.shift
    };
  });
}
function Zl(t) {
  if (t.flip === !1) return;
  const e = typeof t.flip == "string" ? t.flip.split(" ") : void 0;
  return Jt(
    !e || e.every(Yl),
    process.env.NODE_ENV !== "production" && "`flip` expects a spaced-delimited list of placements"
  ), Bl({
    padding: t.overflowPadding,
    fallbackPlacements: e
  });
}
function ql(t) {
  if (!(!t.slide && !t.overlap))
    return $l({
      mainAxis: t.slide,
      crossAxis: t.overlap,
      padding: t.overflowPadding,
      limiter: jl()
    });
}
function Jl(t) {
  return Hl({
    padding: t.overflowPadding,
    apply({ elements: e, availableWidth: n, availableHeight: r, rects: i }) {
      const o = e.floating, s = Math.round(i.reference.width);
      n = Math.floor(n), r = Math.floor(r), o.style.setProperty(
        "--popover-anchor-width",
        `${s}px`
      ), o.style.setProperty(
        "--popover-available-width",
        `${n}px`
      ), o.style.setProperty(
        "--popover-available-height",
        `${r}px`
      ), t.sameWidth && (o.style.width = `${s}px`), t.fitViewport && (o.style.maxWidth = `${n}px`, o.style.maxHeight = `${r}px`);
    }
  });
}
function Ql(t, e) {
  if (t)
    return Wl({
      element: t,
      padding: e.arrowPadding
    });
}
var Ms = gt(
  function({
    store: e,
    modal: n = !1,
    portal: r = n,
    preserveTabOrder: i = !0,
    autoFocusOnShow: o = !0,
    wrapperProps: s,
    fixed: a = !1,
    flip: u = !0,
    shift: c = 0,
    slide: l = !0,
    overlap: f = !1,
    sameWidth: m = !1,
    fitViewport: h = !1,
    gutter: p,
    arrowPadding: d = 4,
    overflowPadding: w = 8,
    getAnchorRect: x,
    updatePosition: v,
    ...b
  }) {
    const g = ls();
    e = e || g, Jt(
      e,
      process.env.NODE_ENV !== "production" && "Popover must receive a `store` prop or be wrapped in a PopoverProvider component."
    );
    const y = $(e, "arrowElement"), E = $(e, "anchorElement"), S = $(e, "disclosureElement"), A = $(e, "popoverElement"), _ = $(e, "contentElement"), T = $(e, "placement"), O = $(e, "mounted"), M = $(e, "rendered"), D = F(null), [I, k] = Y(!1), { portalRef: P, domReady: N } = Ar(r, b.portalRef), L = Q(x), z = Q(v), at = !!v;
    U(() => {
      if (!A?.isConnected) return;
      A.style.setProperty(
        "--popover-overflow-padding",
        `${w}px`
      );
      const tt = Xl(E, L), _t = async () => {
        if (!O) return;
        y || (D.current = D.current || document.createElement("div"));
        const wt = y || D.current, Ut = [
          Kl(wt, { gutter: p, shift: c }),
          Zl({ flip: u, overflowPadding: w }),
          ql({ slide: l, overlap: f, overflowPadding: w }),
          Ql(wt, { arrowPadding: d }),
          Jl({
            sameWidth: m,
            fitViewport: h,
            overflowPadding: w
          })
        ], lt = await zl(tt, A, {
          placement: T,
          strategy: a ? "fixed" : "absolute",
          middleware: Ut
        });
        e?.setState("currentPlacement", lt.placement), k(!0);
        const ee = no(lt.x), _e = no(lt.y);
        if (Object.assign(A.style, {
          top: "0",
          left: "0",
          transform: `translate3d(${ee}px,${_e}px,0)`
        }), wt && lt.middlewareData.arrow) {
          const { x: ne, y: re } = lt.middlewareData.arrow, Te = lt.placement.split("-")[0], ue = wt.clientWidth / 2, le = wt.clientHeight / 2, Je = ne != null ? ne + ue : -ue, Qe = re != null ? re + le : -le;
          A.style.setProperty(
            "--popover-transform-origin",
            {
              top: `${Je}px calc(100% + ${le}px)`,
              bottom: `${Je}px ${-le}px`,
              left: `calc(100% + ${ue}px) ${Qe}px`,
              right: `${-ue}px ${Qe}px`
            }[Te]
          ), Object.assign(wt.style, {
            left: ne != null ? `${ne}px` : "",
            top: re != null ? `${re}px` : "",
            [Te]: "100%"
          });
        }
      }, Gt = kl(tt, A, async () => {
        at ? (await z({ updatePosition: _t }), k(!0)) : await _t();
      }, {
        // JSDOM doesn't support ResizeObserver
        elementResize: typeof ResizeObserver == "function"
      });
      return () => {
        k(!1), Gt();
      };
    }, [
      e,
      M,
      A,
      y,
      E,
      A,
      T,
      O,
      N,
      a,
      u,
      c,
      l,
      f,
      m,
      h,
      p,
      d,
      w,
      L,
      at,
      z
    ]), U(() => {
      if (!O || !N || !A?.isConnected || !_?.isConnected) return;
      const tt = () => {
        A.style.zIndex = getComputedStyle(_).zIndex;
      };
      tt();
      let _t = requestAnimationFrame(() => {
        _t = requestAnimationFrame(tt);
      });
      return () => cancelAnimationFrame(_t);
    }, [O, N, A, _]);
    const yt = a ? "fixed" : "absolute";
    return b = xt(
      b,
      (tt) => /* @__PURE__ */ C(
        "div",
        {
          ...s,
          style: {
            // https://floating-ui.com/docs/computeposition#initial-layout
            position: yt,
            top: 0,
            left: 0,
            width: "max-content",
            ...s?.style
          },
          ref: e?.setPopoverElement,
          children: tt
        }
      ),
      [e, yt, s]
    ), b = xt(
      b,
      (tt) => /* @__PURE__ */ C(fs, { value: e, children: tt }),
      [e]
    ), b = {
      // data-placing is not part of the public API. We're setting this here so
      // we can wait for the popover to be positioned before other components
      // move focus into it. For example, this attribute is observed by the
      // Combobox component with the autoSelect behavior.
      "data-placing": !I || void 0,
      ...b,
      style: {
        position: "relative",
        ...b.style
      }
    }, b = Es({
      store: e,
      modal: n,
      portal: r,
      preserveTabOrder: i,
      preserveTabOrderAnchor: S || E,
      autoFocusOnShow: I && o,
      ...b,
      portalRef: P
    }), b;
  }
);
On(
  st(function(e) {
    const n = Ms(e);
    return ut(Gl, n);
  }),
  ls
);
var tf = "div";
function Fs(t, e, n, r) {
  return jc(e) ? !0 : t ? !!(Z(e, t) || n && Z(n, t) || r?.some((i) => Fs(t, i, n))) : !1;
}
function ef({
  store: t,
  ...e
}) {
  const [n, r] = Y(!1), i = $(t, "mounted");
  V(() => {
    i || r(!1);
  }, [i]);
  const o = e.onFocus, s = Q((u) => {
    o?.(u), !u.defaultPrevented && r(!0);
  }), a = F(null);
  return V(() => Ht(t, ["anchorElement"], (u) => {
    a.current = u.anchorElement;
  }), [t]), e = {
    autoFocusOnHide: n,
    finalFocus: a,
    ...e,
    onFocus: s
  }, e;
}
var ro = kt(null), Ls = gt(
  function({
    store: e,
    modal: n = !1,
    portal: r = n,
    hideOnEscape: i = !0,
    hideOnHoverOutside: o = !0,
    disablePointerEventsOnApproach: s = !!o,
    ...a
  }) {
    const u = kr();
    e = e || u, Jt(
      e,
      process.env.NODE_ENV !== "production" && "Hovercard must receive a `store` prop or be wrapped in a HovercardProvider component."
    );
    const c = F(null), [l, f] = Y([]), m = F(0), h = F(null), { portalRef: p, domReady: d } = Ar(r, a.portalRef), w = Jo(), x = !!o, v = he(o), b = !!s, g = he(
      s
    ), y = $(e, "open"), E = $(e, "mounted");
    V(() => {
      if (!d || !E || !x && !b) return;
      const O = c.current;
      return O ? ht(
        it("mousemove", (D) => {
          if (!e || !w()) return;
          const { anchorElement: I, hideTimeout: k, timeout: P } = e.getState(), N = h.current, [L] = D.composedPath(), z = I;
          if (Fs(L, O, z, l)) {
            h.current = L && z && Z(z, L) ? zn(D) : null, window.clearTimeout(m.current), m.current = 0;
            return;
          }
          if (!m.current) {
            if (N) {
              const at = zn(D), yt = Bi(O, N);
              if ($i(at, yt)) {
                if (h.current = at, !g(D)) return;
                D.preventDefault(), D.stopPropagation();
                return;
              }
            }
            v(D) && (m.current = window.setTimeout(() => {
              m.current = 0, e?.hide();
            }, k ?? P));
          }
        }, !0),
        () => clearTimeout(m.current)
      ) : void 0;
    }, [
      e,
      w,
      d,
      E,
      x,
      b,
      l,
      g,
      v
    ]), V(() => {
      if (!d || !E || !b) return;
      const O = (M) => {
        const D = c.current;
        if (!D) return;
        const I = h.current;
        if (!I) return;
        const k = Bi(D, I);
        if ($i(zn(M), k)) {
          if (!g(M)) return;
          M.preventDefault(), M.stopPropagation();
        }
      };
      return ht(
        // Note: we may need to add pointer events here in the future.
        it("mouseenter", O, !0),
        it("mouseover", O, !0),
        it("mouseout", O, !0),
        it("mouseleave", O, !0)
      );
    }, [d, E, b, g]), V(() => {
      d && (y || e?.setAutoFocusOnShow(!1));
    }, [e, d, y]);
    const S = Ko(y);
    V(() => {
      if (d)
        return () => {
          S.current || e?.setAutoFocusOnShow(!1);
        };
    }, [e, d]);
    const A = vt(ro);
    U(() => {
      if (n || !r || !E || !d) return;
      const O = c.current;
      if (O)
        return A?.(O);
    }, [n, r, E, d]);
    const _ = At(
      (O) => {
        f((D) => [...D, O]);
        const M = A?.(O);
        return () => {
          f(
            (D) => D.filter((I) => I !== O)
          ), M?.();
        };
      },
      [A]
    );
    a = xt(
      a,
      (O) => /* @__PURE__ */ C(ds, { value: e, children: /* @__PURE__ */ C(ro.Provider, { value: _, children: O }) }),
      [e, _]
    ), a = {
      ...a,
      ref: te(c, a.ref)
    }, a = ef({ store: e, ...a });
    const T = $(
      e,
      (O) => n || O.autoFocusOnShow
    );
    return a = Ms({
      store: e,
      modal: n,
      portal: r,
      autoFocusOnShow: T,
      ...a,
      portalRef: p,
      hideOnEscape(O) {
        return gn(i, O) ? !1 : (requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            e?.hide();
          });
        }), !0);
      }
    }), a;
  }
);
On(
  st(function(e) {
    const n = Ls(e);
    return ut(tf, n);
  }),
  kr
);
var nf = "div", rf = gt(
  function({
    store: e,
    portal: n = !0,
    gutter: r = 8,
    preserveTabOrder: i = !1,
    hideOnHoverOutside: o = !0,
    hideOnInteractOutside: s = !0,
    ...a
  }) {
    const u = Vr();
    return e = e || u, Jt(
      e,
      process.env.NODE_ENV !== "production" && "Tooltip must receive a `store` prop or be wrapped in a TooltipProvider component."
    ), a = xt(
      a,
      (l) => /* @__PURE__ */ C(bu, { value: e, children: l }),
      [e]
    ), a = { role: $(
      e,
      (l) => l.type === "description" ? "tooltip" : "none"
    ), ...a }, a = Ls({
      ...a,
      store: e,
      portal: n,
      gutter: r,
      preserveTabOrder: i,
      hideOnHoverOutside(l) {
        if (gn(o, l)) return !1;
        const f = e?.getState().anchorElement;
        return f ? !("focusVisible" in f.dataset) : !0;
      },
      hideOnInteractOutside: (l) => {
        if (gn(s, l)) return !1;
        const f = e?.getState().anchorElement;
        return f ? !Z(f, l.target) : !0;
      }
    }), a;
  }
), of = On(
  st(function(e) {
    const n = rf(e);
    return ut(nf, n);
  }),
  Vr
), sf = "a", ks = gt(
  function({ store: e, showOnHover: n = !0, ...r }) {
    const i = kr();
    e = e || i, Jt(
      e,
      process.env.NODE_ENV !== "production" && "HovercardAnchor must receive a `store` prop or be wrapped in a HovercardProvider component."
    );
    const o = Go(r), s = F(0);
    V(() => () => window.clearTimeout(s.current), []), V(() => it("mouseleave", (d) => {
      if (!e) return;
      const { anchorElement: w } = e.getState();
      w && d.target === w && (window.clearTimeout(s.current), s.current = 0);
    }, !0), [e]);
    const a = r.onMouseMove, u = he(n), c = Jo(), l = Q((p) => {
      if (a?.(p), o || !e || p.defaultPrevented || s.current || !c() || !u(p)) return;
      const d = p.currentTarget;
      e.setAnchorElement(d), e.setDisclosureElement(d);
      const { showTimeout: w, timeout: x } = e.getState(), v = () => {
        s.current = 0, c() && (e?.setAnchorElement(d), e?.show(), queueMicrotask(() => {
          e?.setDisclosureElement(d);
        }));
      }, b = w ?? x;
      b === 0 ? v() : s.current = window.setTimeout(v, b);
    }), f = r.onClick, m = Q((p) => {
      f?.(p), e && (window.clearTimeout(s.current), s.current = 0);
    }), h = At(
      (p) => {
        if (!e) return;
        const { anchorElement: d } = e.getState();
        d?.isConnected || e.setAnchorElement(p);
      },
      [e]
    );
    return r = {
      ...r,
      ref: te(h, r.ref),
      onMouseMove: l,
      onClick: m
    }, r = Tr(r), r;
  }
);
st(function(e) {
  const n = ks(e);
  return ut(sf, n);
});
var af = "div", oe = Yt({
  activeStore: null
});
function io(t) {
  return () => {
    const { activeStore: e } = oe.getState();
    e === t && oe.setState("activeStore", null);
  };
}
var cf = gt(
  function({ store: e, showOnHover: n = !0, ...r }) {
    const i = Vr();
    e = e || i, Jt(
      e,
      process.env.NODE_ENV !== "production" && "TooltipAnchor must receive a `store` prop or be wrapped in a TooltipProvider component."
    );
    const o = F(!1);
    V(() => Ht(e, ["mounted"], (p) => {
      p.mounted || (o.current = !1);
    }), [e]), V(() => {
      if (e)
        return ht(
          // Immediately remove the current store from the global store when
          // the component unmounts. This is useful, for example, to avoid
          // showing tooltips immediately on serial tests.
          io(e),
          Ht(e, ["mounted", "skipTimeout"], (p) => {
            if (!e) return;
            if (p.mounted) {
              const { activeStore: w } = oe.getState();
              return w !== e && w?.hide(), oe.setState("activeStore", e);
            }
            const d = setTimeout(
              io(e),
              p.skipTimeout
            );
            return () => clearTimeout(d);
          })
        );
    }, [e]);
    const s = r.onMouseEnter, a = Q((p) => {
      s?.(p), o.current = !0;
    }), u = r.onFocusVisible, c = Q((p) => {
      u?.(p), !p.defaultPrevented && (e?.setAnchorElement(p.currentTarget), e?.show());
    }), l = r.onBlur, f = Q((p) => {
      if (l?.(p), p.defaultPrevented) return;
      const { activeStore: d } = oe.getState();
      o.current = !1, d === e && oe.setState("activeStore", null);
    }), m = $(e, "type"), h = $(e, (p) => {
      var d;
      return (d = p.contentElement) == null ? void 0 : d.id;
    });
    return r = {
      "aria-labelledby": m === "label" && r["aria-label"] == null ? h : void 0,
      ...r,
      onMouseEnter: a,
      onFocusVisible: c,
      onBlur: f
    }, r = ks({
      store: e,
      showOnHover(p) {
        if (!o.current || gn(n, p)) return !1;
        const { activeStore: d } = oe.getState();
        return d ? (e?.show(), !1) : !0;
      },
      ...r
    }), r;
  }
), uf = st(function(e) {
  const n = cf(e);
  return ut(af, n);
});
function lf({
  popover: t,
  ...e
} = {}) {
  const n = ns(
    e.store,
    es(t, [
      "arrowElement",
      "anchorElement",
      "contentElement",
      "popoverElement",
      "disclosureElement"
    ])
  );
  rs(e, n);
  const r = n?.getState(), i = xs({ ...e, store: n }), o = rt(
    e.placement,
    r?.placement,
    "bottom"
  ), s = {
    ...i.getState(),
    placement: o,
    currentPlacement: o,
    anchorElement: rt(r?.anchorElement, null),
    popoverElement: rt(r?.popoverElement, null),
    arrowElement: rt(r?.arrowElement, null),
    rendered: /* @__PURE__ */ Symbol("rendered")
  }, a = Yt(s, i, n);
  return {
    ...i,
    ...a,
    setAnchorElement: (u) => a.setState("anchorElement", u),
    setPopoverElement: (u) => a.setState("popoverElement", u),
    setArrowElement: (u) => a.setState("arrowElement", u),
    render: () => a.setState("rendered", /* @__PURE__ */ Symbol("rendered"))
  };
}
function ff(t, e, n) {
  return qo(e, [n.popover]), $t(t, n, "placement"), Ss(t, e, n);
}
function df(t = {}) {
  var e;
  const n = (e = t.store) == null ? void 0 : e.getState(), r = lf({
    ...t,
    placement: rt(
      t.placement,
      n?.placement,
      "bottom"
    )
  }), i = rt(t.timeout, n?.timeout, 500), o = {
    ...r.getState(),
    timeout: i,
    showTimeout: rt(t.showTimeout, n?.showTimeout),
    hideTimeout: rt(t.hideTimeout, n?.hideTimeout),
    autoFocusOnShow: rt(n?.autoFocusOnShow, !1)
  }, s = Yt(o, r, t.store);
  return {
    ...r,
    ...s,
    setAutoFocusOnShow: (a) => s.setState("autoFocusOnShow", a)
  };
}
function pf(t, e, n) {
  return $t(t, n, "timeout"), $t(t, n, "showTimeout"), $t(t, n, "hideTimeout"), ff(t, e, n);
}
function mf(t = {}) {
  var e;
  process.env.NODE_ENV !== "production" && t.type === "label" && console.warn(
    "The `type` option on the tooltip store is deprecated.",
    "Render a visually hidden label or use the `aria-label` or `aria-labelledby` attributes on the anchor element instead.",
    "See https://ariakit.com/components/tooltip#tooltip-anchors-must-have-accessible-names"
  );
  const n = (e = t.store) == null ? void 0 : e.getState(), r = df({
    ...t,
    placement: rt(
      t.placement,
      n?.placement,
      "top"
    ),
    hideTimeout: rt(t.hideTimeout, n?.hideTimeout, 0)
  }), i = {
    ...r.getState(),
    type: rt(t.type, n?.type, "description"),
    skipTimeout: rt(t.skipTimeout, n?.skipTimeout, 300)
  }, o = Yt(i, r, t.store);
  return {
    ...r,
    ...o
  };
}
function hf(t, e, n) {
  return $t(t, n, "type"), $t(t, n, "skipTimeout"), pf(t, e, n);
}
function vf(t = {}) {
  const [e, n] = Dr(mf, t);
  return hf(e, n, t);
}
function gf(t) {
  const {
    shortcut: e,
    className: n
  } = t;
  if (!e)
    return null;
  let r, i;
  return typeof e == "string" && (r = e), e !== null && typeof e == "object" && (r = e.display, i = e.ariaLabel), /* @__PURE__ */ C("span", {
    className: n,
    "aria-label": i,
    children: r
  });
}
var yf = gf;
const Yr = /* @__NO_SIDE_EFFECTS__ */ (t) => t;
let bf = Yr, wf = Yr;
process.env.NODE_ENV !== "production" && (bf = (t, e) => {
  !t && typeof console < "u" && console.warn(e);
}, wf = (t, e) => {
  if (!t)
    throw new Error(e);
});
const Vs = (t, e, n) => (((1 - 3 * n + 3 * e) * t + (3 * n - 6 * e)) * t + 3 * e) * t, xf = 1e-7, Sf = 12;
function Ef(t, e, n, r, i) {
  let o, s, a = 0;
  do
    s = e + (n - e) / 2, o = Vs(s, r, i) - t, o > 0 ? n = s : e = s;
  while (Math.abs(o) > xf && ++a < Sf);
  return s;
}
function Af(t, e, n, r) {
  if (t === e && n === r)
    return Yr;
  const i = (o) => Ef(o, 0, 1, t, n);
  return (o) => o === 0 || o === 1 ? o : Vs(i(o), e, r);
}
function Kr(t) {
  return t != null;
}
function ah(t) {
  const e = t === "";
  return !Kr(t) || e;
}
function Pf(t = [], e) {
  return t.find(Kr) ?? e;
}
var Cf = (t) => parseFloat(t), ch = (t) => typeof t == "string" ? Cf(t) : t, oo = {
  initial: void 0,
  /**
   * Defaults to empty string, as that is preferred for usage with
   * <input />, <textarea />, and <select /> form elements.
   */
  fallback: ""
};
function Of(t, e = oo) {
  const {
    initial: n,
    fallback: r
  } = {
    ...oo,
    ...e
  }, [i, o] = Y(t), s = Kr(t);
  V(() => {
    s && i && o(void 0);
  }, [s, i]);
  const a = Pf([t, i, n], r), u = At((c) => {
    s || o(c);
  }, [s]);
  return [a, u];
}
var _f = Of;
function Tf(t, e) {
  const n = F(!1);
  V(() => {
    if (n.current)
      return t();
    n.current = !0;
  }, e), V(() => () => {
    n.current = !1;
  }, []);
}
var $s = Tf;
function Rf(t) {
  if (t.sheet)
    return t.sheet;
  for (var e = 0; e < document.styleSheets.length; e++)
    if (document.styleSheets[e].ownerNode === t)
      return document.styleSheets[e];
}
function Df(t) {
  var e = document.createElement("style");
  return e.setAttribute("data-emotion", t.key), t.nonce !== void 0 && e.setAttribute("nonce", t.nonce), e.appendChild(document.createTextNode("")), e.setAttribute("data-s", ""), e;
}
var If = /* @__PURE__ */ function() {
  function t(n) {
    var r = this;
    this._insertTag = function(i) {
      var o;
      r.tags.length === 0 ? r.insertionPoint ? o = r.insertionPoint.nextSibling : r.prepend ? o = r.container.firstChild : o = r.before : o = r.tags[r.tags.length - 1].nextSibling, r.container.insertBefore(i, o), r.tags.push(i);
    }, this.isSpeedy = n.speedy === void 0 ? !0 : n.speedy, this.tags = [], this.ctr = 0, this.nonce = n.nonce, this.key = n.key, this.container = n.container, this.prepend = n.prepend, this.insertionPoint = n.insertionPoint, this.before = null;
  }
  var e = t.prototype;
  return e.hydrate = function(r) {
    r.forEach(this._insertTag);
  }, e.insert = function(r) {
    this.ctr % (this.isSpeedy ? 65e3 : 1) === 0 && this._insertTag(Df(this));
    var i = this.tags[this.tags.length - 1];
    if (this.isSpeedy) {
      var o = Rf(i);
      try {
        o.insertRule(r, o.cssRules.length);
      } catch {
      }
    } else
      i.appendChild(document.createTextNode(r));
    this.ctr++;
  }, e.flush = function() {
    this.tags.forEach(function(r) {
      var i;
      return (i = r.parentNode) == null ? void 0 : i.removeChild(r);
    }), this.tags = [], this.ctr = 0;
  }, t;
}(), ot = "-ms-", Sn = "-moz-", B = "-webkit-", Bs = "comm", Zr = "rule", qr = "decl", Nf = "@import", Hs = "@keyframes", Mf = "@layer", Ff = Math.abs, In = String.fromCharCode, Lf = Object.assign;
function kf(t, e) {
  return nt(t, 0) ^ 45 ? (((e << 2 ^ nt(t, 0)) << 2 ^ nt(t, 1)) << 2 ^ nt(t, 2)) << 2 ^ nt(t, 3) : 0;
}
function Ws(t) {
  return t.trim();
}
function Vf(t, e) {
  return (t = e.exec(t)) ? t[0] : t;
}
function H(t, e, n) {
  return t.replace(e, n);
}
function dr(t, e) {
  return t.indexOf(e);
}
function nt(t, e) {
  return t.charCodeAt(e) | 0;
}
function He(t, e, n) {
  return t.slice(e, n);
}
function Tt(t) {
  return t.length;
}
function Jr(t) {
  return t.length;
}
function un(t, e) {
  return e.push(t), t;
}
function $f(t, e) {
  return t.map(e).join("");
}
var Nn = 1, we = 1, js = 0, ct = 0, q = 0, Ce = "";
function Mn(t, e, n, r, i, o, s) {
  return { value: t, root: e, parent: n, type: r, props: i, children: o, line: Nn, column: we, length: s, return: "" };
}
function Re(t, e) {
  return Lf(Mn("", null, null, "", null, null, 0), t, { length: -t.length }, e);
}
function Bf() {
  return q;
}
function Hf() {
  return q = ct > 0 ? nt(Ce, --ct) : 0, we--, q === 10 && (we = 1, Nn--), q;
}
function mt() {
  return q = ct < js ? nt(Ce, ct++) : 0, we++, q === 10 && (we = 1, Nn++), q;
}
function Lt() {
  return nt(Ce, ct);
}
function dn() {
  return ct;
}
function Ze(t, e) {
  return He(Ce, t, e);
}
function We(t) {
  switch (t) {
    case 0:
    case 9:
    case 10:
    case 13:
    case 32:
      return 5;
    case 33:
    case 43:
    case 44:
    case 47:
    case 62:
    case 64:
    case 126:
    case 59:
    case 123:
    case 125:
      return 4;
    case 58:
      return 3;
    case 34:
    case 39:
    case 40:
    case 91:
      return 2;
    case 41:
    case 93:
      return 1;
  }
  return 0;
}
function zs(t) {
  return Nn = we = 1, js = Tt(Ce = t), ct = 0, [];
}
function Gs(t) {
  return Ce = "", t;
}
function pn(t) {
  return Ws(Ze(ct - 1, pr(t === 91 ? t + 2 : t === 40 ? t + 1 : t)));
}
function Wf(t) {
  for (; (q = Lt()) && q < 33; )
    mt();
  return We(t) > 2 || We(q) > 3 ? "" : " ";
}
function jf(t, e) {
  for (; --e && mt() && !(q < 48 || q > 102 || q > 57 && q < 65 || q > 70 && q < 97); )
    ;
  return Ze(t, dn() + (e < 6 && Lt() == 32 && mt() == 32));
}
function pr(t) {
  for (; mt(); )
    switch (q) {
      case t:
        return ct;
      case 34:
      case 39:
        t !== 34 && t !== 39 && pr(q);
        break;
      case 40:
        t === 41 && pr(t);
        break;
      case 92:
        mt();
        break;
    }
  return ct;
}
function zf(t, e) {
  for (; mt() && t + q !== 57; )
    if (t + q === 84 && Lt() === 47)
      break;
  return "/*" + Ze(e, ct - 1) + "*" + In(t === 47 ? t : mt());
}
function Gf(t) {
  for (; !We(Lt()); )
    mt();
  return Ze(t, ct);
}
function Uf(t) {
  return Gs(mn("", null, null, null, [""], t = zs(t), 0, [0], t));
}
function mn(t, e, n, r, i, o, s, a, u) {
  for (var c = 0, l = 0, f = s, m = 0, h = 0, p = 0, d = 1, w = 1, x = 1, v = 0, b = "", g = i, y = o, E = r, S = b; w; )
    switch (p = v, v = mt()) {
      case 40:
        if (p != 108 && nt(S, f - 1) == 58) {
          dr(S += H(pn(v), "&", "&\f"), "&\f") != -1 && (x = -1);
          break;
        }
      case 34:
      case 39:
      case 91:
        S += pn(v);
        break;
      case 9:
      case 10:
      case 13:
      case 32:
        S += Wf(p);
        break;
      case 92:
        S += jf(dn() - 1, 7);
        continue;
      case 47:
        switch (Lt()) {
          case 42:
          case 47:
            un(Xf(zf(mt(), dn()), e, n), u);
            break;
          default:
            S += "/";
        }
        break;
      case 123 * d:
        a[c++] = Tt(S) * x;
      case 125 * d:
      case 59:
      case 0:
        switch (v) {
          case 0:
          case 125:
            w = 0;
          case 59 + l:
            x == -1 && (S = H(S, /\f/g, "")), h > 0 && Tt(S) - f && un(h > 32 ? ao(S + ";", r, n, f - 1) : ao(H(S, " ", "") + ";", r, n, f - 2), u);
            break;
          case 59:
            S += ";";
          default:
            if (un(E = so(S, e, n, c, l, i, a, b, g = [], y = [], f), o), v === 123)
              if (l === 0)
                mn(S, e, E, E, g, o, f, a, y);
              else
                switch (m === 99 && nt(S, 3) === 110 ? 100 : m) {
                  case 100:
                  case 108:
                  case 109:
                  case 115:
                    mn(t, E, E, r && un(so(t, E, E, 0, 0, i, a, b, i, g = [], f), y), i, y, f, a, r ? g : y);
                    break;
                  default:
                    mn(S, E, E, E, [""], y, 0, a, y);
                }
        }
        c = l = h = 0, d = x = 1, b = S = "", f = s;
        break;
      case 58:
        f = 1 + Tt(S), h = p;
      default:
        if (d < 1) {
          if (v == 123)
            --d;
          else if (v == 125 && d++ == 0 && Hf() == 125)
            continue;
        }
        switch (S += In(v), v * d) {
          case 38:
            x = l > 0 ? 1 : (S += "\f", -1);
            break;
          case 44:
            a[c++] = (Tt(S) - 1) * x, x = 1;
            break;
          case 64:
            Lt() === 45 && (S += pn(mt())), m = Lt(), l = f = Tt(b = S += Gf(dn())), v++;
            break;
          case 45:
            p === 45 && Tt(S) == 2 && (d = 0);
        }
    }
  return o;
}
function so(t, e, n, r, i, o, s, a, u, c, l) {
  for (var f = i - 1, m = i === 0 ? o : [""], h = Jr(m), p = 0, d = 0, w = 0; p < r; ++p)
    for (var x = 0, v = He(t, f + 1, f = Ff(d = s[p])), b = t; x < h; ++x)
      (b = Ws(d > 0 ? m[x] + " " + v : H(v, /&\f/g, m[x]))) && (u[w++] = b);
  return Mn(t, e, n, i === 0 ? Zr : a, u, c, l);
}
function Xf(t, e, n) {
  return Mn(t, e, n, Bs, In(Bf()), He(t, 2, -2), 0);
}
function ao(t, e, n, r) {
  return Mn(t, e, n, qr, He(t, 0, r), He(t, r + 1, -1), r);
}
function ge(t, e) {
  for (var n = "", r = Jr(t), i = 0; i < r; i++)
    n += e(t[i], i, t, e) || "";
  return n;
}
function Yf(t, e, n, r) {
  switch (t.type) {
    case Mf:
      if (t.children.length) break;
    case Nf:
    case qr:
      return t.return = t.return || t.value;
    case Bs:
      return "";
    case Hs:
      return t.return = t.value + "{" + ge(t.children, r) + "}";
    case Zr:
      t.value = t.props.join(",");
  }
  return Tt(n = ge(t.children, r)) ? t.return = t.value + "{" + n + "}" : "";
}
function Kf(t) {
  var e = Jr(t);
  return function(n, r, i, o) {
    for (var s = "", a = 0; a < e; a++)
      s += t[a](n, r, i, o) || "";
    return s;
  };
}
function Zf(t) {
  return function(e) {
    e.root || (e = e.return) && t(e);
  };
}
function Us(t) {
  var e = /* @__PURE__ */ Object.create(null);
  return function(n) {
    return e[n] === void 0 && (e[n] = t(n)), e[n];
  };
}
var qf = function(e, n, r) {
  for (var i = 0, o = 0; i = o, o = Lt(), i === 38 && o === 12 && (n[r] = 1), !We(o); )
    mt();
  return Ze(e, ct);
}, Jf = function(e, n) {
  var r = -1, i = 44;
  do
    switch (We(i)) {
      case 0:
        i === 38 && Lt() === 12 && (n[r] = 1), e[r] += qf(ct - 1, n, r);
        break;
      case 2:
        e[r] += pn(i);
        break;
      case 4:
        if (i === 44) {
          e[++r] = Lt() === 58 ? "&\f" : "", n[r] = e[r].length;
          break;
        }
      default:
        e[r] += In(i);
    }
  while (i = mt());
  return e;
}, Qf = function(e, n) {
  return Gs(Jf(zs(e), n));
}, co = /* @__PURE__ */ new WeakMap(), td = function(e) {
  if (!(e.type !== "rule" || !e.parent || // positive .length indicates that this rule contains pseudo
  // negative .length indicates that this rule has been already prefixed
  e.length < 1)) {
    for (var n = e.value, r = e.parent, i = e.column === r.column && e.line === r.line; r.type !== "rule"; )
      if (r = r.parent, !r) return;
    if (!(e.props.length === 1 && n.charCodeAt(0) !== 58 && !co.get(r)) && !i) {
      co.set(e, !0);
      for (var o = [], s = Qf(n, o), a = r.props, u = 0, c = 0; u < s.length; u++)
        for (var l = 0; l < a.length; l++, c++)
          e.props[c] = o[u] ? s[u].replace(/&\f/g, a[l]) : a[l] + " " + s[u];
    }
  }
}, ed = function(e) {
  if (e.type === "decl") {
    var n = e.value;
    // charcode for l
    n.charCodeAt(0) === 108 && // charcode for b
    n.charCodeAt(2) === 98 && (e.return = "", e.value = "");
  }
};
function Xs(t, e) {
  switch (kf(t, e)) {
    case 5103:
      return B + "print-" + t + t;
    case 5737:
    case 4201:
    case 3177:
    case 3433:
    case 1641:
    case 4457:
    case 2921:
    case 5572:
    case 6356:
    case 5844:
    case 3191:
    case 6645:
    case 3005:
    case 6391:
    case 5879:
    case 5623:
    case 6135:
    case 4599:
    case 4855:
    case 4215:
    case 6389:
    case 5109:
    case 5365:
    case 5621:
    case 3829:
      return B + t + t;
    case 5349:
    case 4246:
    case 4810:
    case 6968:
    case 2756:
      return B + t + Sn + t + ot + t + t;
    case 6828:
    case 4268:
      return B + t + ot + t + t;
    case 6165:
      return B + t + ot + "flex-" + t + t;
    case 5187:
      return B + t + H(t, /(\w+).+(:[^]+)/, B + "box-$1$2" + ot + "flex-$1$2") + t;
    case 5443:
      return B + t + ot + "flex-item-" + H(t, /flex-|-self/, "") + t;
    case 4675:
      return B + t + ot + "flex-line-pack" + H(t, /align-content|flex-|-self/, "") + t;
    case 5548:
      return B + t + ot + H(t, "shrink", "negative") + t;
    case 5292:
      return B + t + ot + H(t, "basis", "preferred-size") + t;
    case 6060:
      return B + "box-" + H(t, "-grow", "") + B + t + ot + H(t, "grow", "positive") + t;
    case 4554:
      return B + H(t, /([^-])(transform)/g, "$1" + B + "$2") + t;
    case 6187:
      return H(H(H(t, /(zoom-|grab)/, B + "$1"), /(image-set)/, B + "$1"), t, "") + t;
    case 5495:
    case 3959:
      return H(t, /(image-set\([^]*)/, B + "$1$`$1");
    case 4968:
      return H(H(t, /(.+:)(flex-)?(.*)/, B + "box-pack:$3" + ot + "flex-pack:$3"), /s.+-b[^;]+/, "justify") + B + t + t;
    case 4095:
    case 3583:
    case 4068:
    case 2532:
      return H(t, /(.+)-inline(.+)/, B + "$1$2") + t;
    case 8116:
    case 7059:
    case 5753:
    case 5535:
    case 5445:
    case 5701:
    case 4933:
    case 4677:
    case 5533:
    case 5789:
    case 5021:
    case 4765:
      if (Tt(t) - 1 - e > 6) switch (nt(t, e + 1)) {
        case 109:
          if (nt(t, e + 4) !== 45) break;
        case 102:
          return H(t, /(.+:)(.+)-([^]+)/, "$1" + B + "$2-$3$1" + Sn + (nt(t, e + 3) == 108 ? "$3" : "$2-$3")) + t;
        case 115:
          return ~dr(t, "stretch") ? Xs(H(t, "stretch", "fill-available"), e) + t : t;
      }
      break;
    case 4949:
      if (nt(t, e + 1) !== 115) break;
    case 6444:
      switch (nt(t, Tt(t) - 3 - (~dr(t, "!important") && 10))) {
        case 107:
          return H(t, ":", ":" + B) + t;
        case 101:
          return H(t, /(.+:)([^;!]+)(;|!.+)?/, "$1" + B + (nt(t, 14) === 45 ? "inline-" : "") + "box$3$1" + B + "$2$3$1" + ot + "$2box$3") + t;
      }
      break;
    case 5936:
      switch (nt(t, e + 11)) {
        case 114:
          return B + t + ot + H(t, /[svh]\w+-[tblr]{2}/, "tb") + t;
        case 108:
          return B + t + ot + H(t, /[svh]\w+-[tblr]{2}/, "tb-rl") + t;
        case 45:
          return B + t + ot + H(t, /[svh]\w+-[tblr]{2}/, "lr") + t;
      }
      return B + t + ot + t + t;
  }
  return t;
}
var nd = function(e, n, r, i) {
  if (e.length > -1 && !e.return) switch (e.type) {
    case qr:
      e.return = Xs(e.value, e.length);
      break;
    case Hs:
      return ge([Re(e, {
        value: H(e.value, "@", "@" + B)
      })], i);
    case Zr:
      if (e.length) return $f(e.props, function(o) {
        switch (Vf(o, /(::plac\w+|:read-\w+)/)) {
          case ":read-only":
          case ":read-write":
            return ge([Re(e, {
              props: [H(o, /:(read-\w+)/, ":" + Sn + "$1")]
            })], i);
          case "::placeholder":
            return ge([Re(e, {
              props: [H(o, /:(plac\w+)/, ":" + B + "input-$1")]
            }), Re(e, {
              props: [H(o, /:(plac\w+)/, ":" + Sn + "$1")]
            }), Re(e, {
              props: [H(o, /:(plac\w+)/, ot + "input-$1")]
            })], i);
        }
        return "";
      });
  }
}, rd = [nd], Qr = function(e) {
  var n = e.key;
  if (n === "css") {
    var r = document.querySelectorAll("style[data-emotion]:not([data-s])");
    Array.prototype.forEach.call(r, function(d) {
      var w = d.getAttribute("data-emotion");
      w.indexOf(" ") !== -1 && (document.head.appendChild(d), d.setAttribute("data-s", ""));
    });
  }
  var i = e.stylisPlugins || rd, o = {}, s, a = [];
  s = e.container || document.head, Array.prototype.forEach.call(
    // this means we will ignore elements which don't have a space in them which
    // means that the style elements we're looking at are only Emotion 11 server-rendered style elements
    document.querySelectorAll('style[data-emotion^="' + n + ' "]'),
    function(d) {
      for (var w = d.getAttribute("data-emotion").split(" "), x = 1; x < w.length; x++)
        o[w[x]] = !0;
      a.push(d);
    }
  );
  var u, c = [td, ed];
  {
    var l, f = [Yf, Zf(function(d) {
      l.insert(d);
    })], m = Kf(c.concat(i, f)), h = function(w) {
      return ge(Uf(w), m);
    };
    u = function(w, x, v, b) {
      l = v, h(w ? w + "{" + x.styles + "}" : x.styles), b && (p.inserted[x.name] = !0);
    };
  }
  var p = {
    key: n,
    sheet: new If({
      key: n,
      container: s,
      nonce: e.nonce,
      speedy: e.speedy,
      prepend: e.prepend,
      insertionPoint: e.insertionPoint
    }),
    nonce: e.nonce,
    inserted: o,
    registered: {},
    insert: u
  };
  return p.sheet.hydrate(a), p;
};
function mr() {
  return mr = Object.assign ? Object.assign.bind() : function(t) {
    for (var e = 1; e < arguments.length; e++) {
      var n = arguments[e];
      for (var r in n) ({}).hasOwnProperty.call(n, r) && (t[r] = n[r]);
    }
    return t;
  }, mr.apply(null, arguments);
}
var id = !0;
function Fn(t, e, n) {
  var r = "";
  return n.split(" ").forEach(function(i) {
    t[i] !== void 0 ? e.push(t[i] + ";") : i && (r += i + " ");
  }), r;
}
var ti = function(e, n, r) {
  var i = e.key + "-" + n.name;
  // we only need to add the styles to the registered cache if the
  // class name could be used further down
  // the tree but if it's a string tag, we know it won't
  // so we don't have to add it to registered cache.
  // this improves memory usage since we can avoid storing the whole style string
  (r === !1 || // we need to always store it if we're in compat mode and
  // in node since emotion-server relies on whether a style is in
  // the registered cache to know whether a style is global or not
  // also, note that this check will be dead code eliminated in the browser
  id === !1) && e.registered[i] === void 0 && (e.registered[i] = n.styles);
}, Ln = function(e, n, r) {
  ti(e, n, r);
  var i = e.key + "-" + n.name;
  if (e.inserted[n.name] === void 0) {
    var o = n;
    do
      e.insert(n === o ? "." + i : "", o, e.sheet, !0), o = o.next;
    while (o !== void 0);
  }
};
function od(t) {
  for (var e = 0, n, r = 0, i = t.length; i >= 4; ++r, i -= 4)
    n = t.charCodeAt(r) & 255 | (t.charCodeAt(++r) & 255) << 8 | (t.charCodeAt(++r) & 255) << 16 | (t.charCodeAt(++r) & 255) << 24, n = /* Math.imul(k, m): */
    (n & 65535) * 1540483477 + ((n >>> 16) * 59797 << 16), n ^= /* k >>> r: */
    n >>> 24, e = /* Math.imul(k, m): */
    (n & 65535) * 1540483477 + ((n >>> 16) * 59797 << 16) ^ /* Math.imul(h, m): */
    (e & 65535) * 1540483477 + ((e >>> 16) * 59797 << 16);
  switch (i) {
    case 3:
      e ^= (t.charCodeAt(r + 2) & 255) << 16;
    case 2:
      e ^= (t.charCodeAt(r + 1) & 255) << 8;
    case 1:
      e ^= t.charCodeAt(r) & 255, e = /* Math.imul(h, m): */
      (e & 65535) * 1540483477 + ((e >>> 16) * 59797 << 16);
  }
  return e ^= e >>> 13, e = /* Math.imul(h, m): */
  (e & 65535) * 1540483477 + ((e >>> 16) * 59797 << 16), ((e ^ e >>> 15) >>> 0).toString(36);
}
var sd = {
  animationIterationCount: 1,
  aspectRatio: 1,
  borderImageOutset: 1,
  borderImageSlice: 1,
  borderImageWidth: 1,
  boxFlex: 1,
  boxFlexGroup: 1,
  boxOrdinalGroup: 1,
  columnCount: 1,
  columns: 1,
  flex: 1,
  flexGrow: 1,
  flexPositive: 1,
  flexShrink: 1,
  flexNegative: 1,
  flexOrder: 1,
  gridRow: 1,
  gridRowEnd: 1,
  gridRowSpan: 1,
  gridRowStart: 1,
  gridColumn: 1,
  gridColumnEnd: 1,
  gridColumnSpan: 1,
  gridColumnStart: 1,
  msGridRow: 1,
  msGridRowSpan: 1,
  msGridColumn: 1,
  msGridColumnSpan: 1,
  fontWeight: 1,
  lineHeight: 1,
  opacity: 1,
  order: 1,
  orphans: 1,
  scale: 1,
  tabSize: 1,
  widows: 1,
  zIndex: 1,
  zoom: 1,
  WebkitLineClamp: 1,
  // SVG-related properties
  fillOpacity: 1,
  floodOpacity: 1,
  stopOpacity: 1,
  strokeDasharray: 1,
  strokeDashoffset: 1,
  strokeMiterlimit: 1,
  strokeOpacity: 1,
  strokeWidth: 1
}, ad = /[A-Z]|^ms/g, cd = /_EMO_([^_]+?)_([^]*?)_EMO_/g, Ys = function(e) {
  return e.charCodeAt(1) === 45;
}, uo = function(e) {
  return e != null && typeof e != "boolean";
}, Zn = /* @__PURE__ */ Us(function(t) {
  return Ys(t) ? t : t.replace(ad, "-$&").toLowerCase();
}), lo = function(e, n) {
  switch (e) {
    case "animation":
    case "animationName":
      if (typeof n == "string")
        return n.replace(cd, function(r, i, o) {
          return Rt = {
            name: i,
            styles: o,
            next: Rt
          }, i;
        });
  }
  return sd[e] !== 1 && !Ys(e) && typeof n == "number" && n !== 0 ? n + "px" : n;
};
function je(t, e, n) {
  if (n == null)
    return "";
  var r = n;
  if (r.__emotion_styles !== void 0)
    return r;
  switch (typeof n) {
    case "boolean":
      return "";
    case "object": {
      var i = n;
      if (i.anim === 1)
        return Rt = {
          name: i.name,
          styles: i.styles,
          next: Rt
        }, i.name;
      var o = n;
      if (o.styles !== void 0) {
        var s = o.next;
        if (s !== void 0)
          for (; s !== void 0; )
            Rt = {
              name: s.name,
              styles: s.styles,
              next: Rt
            }, s = s.next;
        var a = o.styles + ";";
        return a;
      }
      return ud(t, e, n);
    }
    case "function": {
      if (t !== void 0) {
        var u = Rt, c = n(t);
        return Rt = u, je(t, e, c);
      }
      break;
    }
  }
  var l = n;
  if (e == null)
    return l;
  var f = e[l];
  return f !== void 0 ? f : l;
}
function ud(t, e, n) {
  var r = "";
  if (Array.isArray(n))
    for (var i = 0; i < n.length; i++)
      r += je(t, e, n[i]) + ";";
  else
    for (var o in n) {
      var s = n[o];
      if (typeof s != "object") {
        var a = s;
        e != null && e[a] !== void 0 ? r += o + "{" + e[a] + "}" : uo(a) && (r += Zn(o) + ":" + lo(o, a) + ";");
      } else if (Array.isArray(s) && typeof s[0] == "string" && (e == null || e[s[0]] === void 0))
        for (var u = 0; u < s.length; u++)
          uo(s[u]) && (r += Zn(o) + ":" + lo(o, s[u]) + ";");
      else {
        var c = je(t, e, s);
        switch (o) {
          case "animation":
          case "animationName": {
            r += Zn(o) + ":" + c + ";";
            break;
          }
          default:
            r += o + "{" + c + "}";
        }
      }
    }
  return r;
}
var fo = /label:\s*([^\s;{]+)\s*(;|$)/g, Rt;
function Ne(t, e, n) {
  if (t.length === 1 && typeof t[0] == "object" && t[0] !== null && t[0].styles !== void 0)
    return t[0];
  var r = !0, i = "";
  Rt = void 0;
  var o = t[0];
  if (o == null || o.raw === void 0)
    r = !1, i += je(n, e, o);
  else {
    var s = o;
    i += s[0];
  }
  for (var a = 1; a < t.length; a++)
    if (i += je(n, e, t[a]), r) {
      var u = o;
      i += u[a];
    }
  fo.lastIndex = 0;
  for (var c = "", l; (l = fo.exec(i)) !== null; )
    c += "-" + l[1];
  var f = od(i) + c;
  return {
    name: f,
    styles: i,
    next: Rt
  };
}
var ld = function(e) {
  return e();
}, fd = W.useInsertionEffect ? W.useInsertionEffect : !1, Ks = fd || ld, ei = /* @__PURE__ */ W.createContext(
  // we're doing this to avoid preconstruct's dead code elimination in this one case
  // because this module is primarily intended for the browser and node
  // but it's also required in react native and similar environments sometimes
  // and we could have a special build just for that
  // but this is much easier and the native packages
  // might use a different theme context in the future anyway
  typeof HTMLElement < "u" ? /* @__PURE__ */ Qr({
    key: "css"
  }) : null
), dd = ei.Provider, pd = function() {
  return vt(ei);
}, Zs = function(e) {
  return /* @__PURE__ */ Ot(function(n, r) {
    var i = vt(ei);
    return e(n, i, r);
  });
}, qs = /* @__PURE__ */ W.createContext({}), Js = {}.hasOwnProperty, hr = "__EMOTION_TYPE_PLEASE_DO_NOT_USE__", uh = function(e, n) {
  var r = {};
  for (var i in n)
    Js.call(n, i) && (r[i] = n[i]);
  return r[hr] = e, r;
}, md = function(e) {
  var n = e.cache, r = e.serialized, i = e.isStringTag;
  return ti(n, r, i), Ks(function() {
    return Ln(n, r, i);
  }), null;
}, hd = /* @__PURE__ */ Zs(function(t, e, n) {
  var r = t.css;
  typeof r == "string" && e.registered[r] !== void 0 && (r = e.registered[r]);
  var i = t[hr], o = [r], s = "";
  typeof t.className == "string" ? s = Fn(e.registered, o, t.className) : t.className != null && (s = t.className + " ");
  var a = Ne(o, void 0, W.useContext(qs));
  s += e.key + "-" + a.name;
  var u = {};
  for (var c in t)
    Js.call(t, c) && c !== "css" && c !== hr && (u[c] = t[c]);
  return u.className = s, n && (u.ref = n), /* @__PURE__ */ W.createElement(W.Fragment, null, /* @__PURE__ */ W.createElement(md, {
    cache: e,
    serialized: a,
    isStringTag: typeof i == "string"
  }), /* @__PURE__ */ W.createElement(i, u));
}), lh = hd;
function po(t, e) {
  if (t.inserted[e.name] === void 0)
    return t.insert("", e, t.sheet, !0);
}
function mo(t, e, n) {
  var r = [], i = Fn(t, r, n);
  return r.length < 2 ? n : i + e(r);
}
var vd = function(e) {
  var n = Qr(e);
  n.sheet.speedy = function(a) {
    this.isSpeedy = a;
  }, n.compat = !0;
  var r = function() {
    for (var u = arguments.length, c = new Array(u), l = 0; l < u; l++)
      c[l] = arguments[l];
    var f = Ne(c, n.registered, void 0);
    return Ln(n, f, !1), n.key + "-" + f.name;
  }, i = function() {
    for (var u = arguments.length, c = new Array(u), l = 0; l < u; l++)
      c[l] = arguments[l];
    var f = Ne(c, n.registered), m = "animation-" + f.name;
    return po(n, {
      name: f.name,
      styles: "@keyframes " + m + "{" + f.styles + "}"
    }), m;
  }, o = function() {
    for (var u = arguments.length, c = new Array(u), l = 0; l < u; l++)
      c[l] = arguments[l];
    var f = Ne(c, n.registered);
    po(n, f);
  }, s = function() {
    for (var u = arguments.length, c = new Array(u), l = 0; l < u; l++)
      c[l] = arguments[l];
    return mo(n.registered, r, gd(c));
  };
  return {
    css: r,
    cx: s,
    injectGlobal: o,
    keyframes: i,
    hydrate: function(u) {
      u.forEach(function(c) {
        n.inserted[c] = !0;
      });
    },
    flush: function() {
      n.registered = {}, n.inserted = {}, n.sheet.flush();
    },
    sheet: n.sheet,
    cache: n,
    getRegisteredStyles: Fn.bind(null, n.registered),
    merge: mo.bind(null, n.registered, r)
  };
}, gd = function t(e) {
  for (var n = "", r = 0; r < e.length; r++) {
    var i = e[r];
    if (i != null) {
      var o = void 0;
      switch (typeof i) {
        case "boolean":
          break;
        case "object": {
          if (Array.isArray(i))
            o = t(i);
          else {
            o = "";
            for (var s in i)
              i[s] && s && (o && (o += " "), o += s);
          }
          break;
        }
        default:
          o = i;
      }
      o && (n && (n += " "), n += o);
    }
  }
  return n;
}, yd = vd({
  key: "css"
}), bd = yd.cx, wd = (t) => typeof t < "u" && t !== null && ["name", "styles"].every((e) => typeof t[e] < "u"), xd = () => {
  const t = pd();
  return At((...n) => {
    if (t === null)
      throw new Error("The `useCx` hook should be only used within a valid Emotion Cache Context");
    return bd(...n.map((r) => wd(r) ? (Ln(t, r, !1), `${t.key}-${r.name}`) : r));
  }, [t]);
};
function Sd(t, e) {
  var n = 0, r, i;
  e = e || {};
  function o() {
    var s = r, a = arguments.length, u, c;
    t: for (; s; ) {
      if (s.args.length !== arguments.length) {
        s = s.next;
        continue;
      }
      for (c = 0; c < a; c++)
        if (s.args[c] !== arguments[c]) {
          s = s.next;
          continue t;
        }
      return s !== r && (s === i && (i = s.prev), s.prev.next = s.next, s.next && (s.next.prev = s.prev), s.next = r, s.prev = null, r.prev = s, r = s), s.val;
    }
    for (u = new Array(a), c = 0; c < a; c++)
      u[c] = arguments[c];
    return s = {
      args: u,
      // Generate the result from original function
      val: t.apply(null, u)
    }, r ? (r.prev = s, s.next = r) : i = s, n === /** @type {MemizeOptions} */
    e.maxSize ? (i = /** @type {MemizeCacheNode} */
    i.prev, i.next = null) : n++, r = s, s.val;
  }
  return o.clear = function() {
    r = null, i = null, n = 0;
  }, o;
}
var Dt = Object.freeze({
  SLIDE_DISTANCE: 4,
  SLIDE_DURATION: 200,
  SLIDE_EASING: {
    function: "cubic-bezier",
    args: [0, 0, 0, 1]
  },
  FADE_DURATION: 80,
  FADE_EASING: {
    function: "linear"
  }
}), ho = (t) => t.args?.length ? `${t.function}(${t.args.join(",")})` : t.function, fh = Object.freeze({
  SLIDE_DISTANCE: `${Dt.SLIDE_DISTANCE}px`,
  SLIDE_DURATION: `${Dt.SLIDE_DURATION}ms`,
  SLIDE_EASING: ho(Dt.SLIDE_EASING),
  FADE_DURATION: `${Dt.FADE_DURATION}ms`,
  FADE_EASING: ho(Dt.FADE_EASING)
}), Ed = {
  bottom: "bottom",
  top: "top",
  "middle left": "left",
  "middle right": "right",
  "bottom left": "bottom-end",
  "bottom center": "bottom",
  "bottom right": "bottom-start",
  "top left": "top-end",
  "top center": "top",
  "top right": "top-start",
  "middle left left": "left",
  "middle left right": "left",
  "middle left bottom": "left-end",
  "middle left top": "left-start",
  "middle right left": "right",
  "middle right right": "right",
  "middle right bottom": "right-end",
  "middle right top": "right-start",
  "bottom left left": "bottom-end",
  "bottom left right": "bottom-end",
  "bottom left bottom": "bottom-end",
  "bottom left top": "bottom-end",
  "bottom center left": "bottom",
  "bottom center right": "bottom",
  "bottom center bottom": "bottom",
  "bottom center top": "bottom",
  "bottom right left": "bottom-start",
  "bottom right right": "bottom-start",
  "bottom right bottom": "bottom-start",
  "bottom right top": "bottom-start",
  "top left left": "top-end",
  "top left right": "top-end",
  "top left bottom": "top-end",
  "top left top": "top-end",
  "top center left": "top",
  "top center right": "top",
  "top center bottom": "top",
  "top center top": "top",
  "top right left": "top-start",
  "top right right": "top-start",
  "top right bottom": "top-start",
  "top right top": "top-start",
  // `middle`/`middle center [corner?]` positions are associated to a fallback
  // `bottom` placement because there aren't any corresponding placement values.
  middle: "bottom",
  "middle center": "bottom",
  "middle center bottom": "bottom",
  "middle center left": "bottom",
  "middle center right": "bottom",
  "middle center top": "bottom"
}, Qs = (t) => Ed[t] ?? "bottom", Ad = {
  top: {
    originX: 0.5,
    originY: 1
  },
  // open from bottom, center
  "top-start": {
    originX: 0,
    originY: 1
  },
  // open from bottom, left
  "top-end": {
    originX: 1,
    originY: 1
  },
  // open from bottom, right
  right: {
    originX: 0,
    originY: 0.5
  },
  // open from middle, left
  "right-start": {
    originX: 0,
    originY: 0
  },
  // open from top, left
  "right-end": {
    originX: 0,
    originY: 1
  },
  // open from bottom, left
  bottom: {
    originX: 0.5,
    originY: 0
  },
  // open from top, center
  "bottom-start": {
    originX: 0,
    originY: 0
  },
  // open from top, left
  "bottom-end": {
    originX: 1,
    originY: 0
  },
  // open from top, right
  left: {
    originX: 1,
    originY: 0.5
  },
  // open from middle, right
  "left-start": {
    originX: 1,
    originY: 0
  },
  // open from top, right
  "left-end": {
    originX: 1,
    originY: 1
  },
  // open from bottom, right
  overlay: {
    originX: 0.5,
    originY: 0.5
  }
  // open from center, center
}, dh = (t) => {
  const e = t.startsWith("top") || t.startsWith("bottom") ? "translateY" : "translateX", n = t.startsWith("top") || t.startsWith("left") ? 1 : -1;
  return {
    style: Ad[t],
    initial: {
      opacity: 0,
      [e]: `${Dt.SLIDE_DISTANCE * n}px`
    },
    animate: {
      opacity: 1,
      [e]: 0
    },
    transition: {
      opacity: {
        duration: Dt.FADE_DURATION / 1e3,
        ease: Dt.FADE_EASING.function
      },
      [e]: {
        duration: Dt.SLIDE_DURATION / 1e3,
        ease: Af(...Dt.SLIDE_EASING.args)
      }
    }
  };
};
function Pd(t) {
  return !!t?.top;
}
function Cd(t) {
  return !!t?.current;
}
var ph = ({
  anchor: t,
  anchorRef: e,
  anchorRect: n,
  getAnchorRect: r,
  fallbackReferenceElement: i
}) => {
  let o = null;
  return t ? o = t : Pd(e) ? o = {
    getBoundingClientRect() {
      const s = e.top.getBoundingClientRect(), a = e.bottom.getBoundingClientRect();
      return new window.DOMRect(s.x, s.y, s.width, a.bottom - s.top);
    }
  } : Cd(e) ? o = e.current : e ? o = e : n ? o = {
    getBoundingClientRect() {
      return n;
    }
  } : r ? o = {
    getBoundingClientRect() {
      const s = r(i);
      return new window.DOMRect(s.x ?? s.left, s.y ?? s.top, s.width ?? s.right - s.left, s.height ?? s.bottom - s.top);
    }
  } : i && (o = i.parentElement), o ?? null;
}, mh = (t) => t === null || Number.isNaN(t) ? void 0 : Math.round(t), vr = kt({
  isNestedInTooltip: !1
});
vr.displayName = "TooltipInternalContext";
var Od = 700, _d = {
  isNestedInTooltip: !0
};
function Td(t, e) {
  const {
    children: n,
    className: r,
    delay: i = Od,
    hideOnClick: o = !0,
    placement: s,
    position: a,
    shortcut: u,
    text: c,
    ...l
  } = t, {
    isNestedInTooltip: f
  } = vt(vr), m = jo(ta, "tooltip"), h = c || u ? m : void 0, p = gr.count(n) === 1;
  p || process.env.NODE_ENV === "development" && console.error("wp-components.Tooltip should be called with only a single child element.");
  let d;
  s !== void 0 ? d = s : a !== void 0 && (d = Qs(a), Wo("`position` prop in wp.components.tooltip", {
    since: "6.4",
    alternative: "`placement` prop"
  })), d = d || "top";
  const w = vf({
    placement: d,
    showTimeout: i
  }), x = $(w, "mounted");
  if (f)
    return p ? /* @__PURE__ */ C(yn, {
      ...l,
      render: n
    }) : n;
  function v(b) {
    return h && x && b.props["aria-describedby"] === void 0 && b.props["aria-label"] !== c ? Le(b, {
      "aria-describedby": h
    }) : b;
  }
  return /* @__PURE__ */ Et(vr.Provider, {
    value: _d,
    children: [/* @__PURE__ */ C(uf, {
      onClick: o ? w.hide : void 0,
      store: w,
      render: p ? v(n) : void 0,
      ref: e,
      children: p ? void 0 : n
    }), p && (c || u) && /* @__PURE__ */ Et(of, {
      ...l,
      className: Pn("components-tooltip", r),
      unmountOnHide: !0,
      gutter: 4,
      id: h,
      overflowPadding: 0.5,
      store: w,
      children: [c, u && /* @__PURE__ */ C(yf, {
        className: c ? "components-tooltip__shortcut" : "",
        shortcut: u
      })]
    })]
  });
}
var ta = Ot(Td), Rd = ta, Dd = function(e) {
  return Id(e) && !Nd(e);
};
function Id(t) {
  return !!t && typeof t == "object";
}
function Nd(t) {
  var e = Object.prototype.toString.call(t);
  return e === "[object RegExp]" || e === "[object Date]" || Ld(t);
}
var Md = typeof Symbol == "function" && Symbol.for, Fd = Md ? Symbol.for("react.element") : 60103;
function Ld(t) {
  return t.$$typeof === Fd;
}
function kd(t) {
  return Array.isArray(t) ? [] : {};
}
function ze(t, e) {
  return e.clone !== !1 && e.isMergeableObject(t) ? xe(kd(t), t, e) : t;
}
function Vd(t, e, n) {
  return t.concat(e).map(function(r) {
    return ze(r, n);
  });
}
function $d(t, e) {
  if (!e.customMerge)
    return xe;
  var n = e.customMerge(t);
  return typeof n == "function" ? n : xe;
}
function Bd(t) {
  return Object.getOwnPropertySymbols ? Object.getOwnPropertySymbols(t).filter(function(e) {
    return Object.propertyIsEnumerable.call(t, e);
  }) : [];
}
function vo(t) {
  return Object.keys(t).concat(Bd(t));
}
function ea(t, e) {
  try {
    return e in t;
  } catch {
    return !1;
  }
}
function Hd(t, e) {
  return ea(t, e) && !(Object.hasOwnProperty.call(t, e) && Object.propertyIsEnumerable.call(t, e));
}
function Wd(t, e, n) {
  var r = {};
  return n.isMergeableObject(t) && vo(t).forEach(function(i) {
    r[i] = ze(t[i], n);
  }), vo(e).forEach(function(i) {
    Hd(t, i) || (ea(t, i) && n.isMergeableObject(e[i]) ? r[i] = $d(i, n)(t[i], e[i], n) : r[i] = ze(e[i], n));
  }), r;
}
function xe(t, e, n) {
  n = n || {}, n.arrayMerge = n.arrayMerge || Vd, n.isMergeableObject = n.isMergeableObject || Dd, n.cloneUnlessOtherwiseSpecified = ze;
  var r = Array.isArray(e), i = Array.isArray(t), o = r === i;
  return o ? r ? n.arrayMerge(t, e, n) : Wd(t, e, n) : ze(e, n);
}
xe.all = function(e, n) {
  if (!Array.isArray(e))
    throw new Error("first argument should be an array");
  return e.reduce(function(r, i) {
    return xe(r, i, n);
  }, {});
};
var jd = xe, zd = jd;
const Gd = /* @__PURE__ */ Do(zd);
var Ud = function t(e, n) {
  if (e === n) return !0;
  if (e && n && typeof e == "object" && typeof n == "object") {
    if (e.constructor !== n.constructor) return !1;
    var r, i, o;
    if (Array.isArray(e)) {
      if (r = e.length, r != n.length) return !1;
      for (i = r; i-- !== 0; )
        if (!t(e[i], n[i])) return !1;
      return !0;
    }
    if (e instanceof Map && n instanceof Map) {
      if (e.size !== n.size) return !1;
      for (i of e.entries())
        if (!n.has(i[0])) return !1;
      for (i of e.entries())
        if (!t(i[1], n.get(i[0]))) return !1;
      return !0;
    }
    if (e instanceof Set && n instanceof Set) {
      if (e.size !== n.size) return !1;
      for (i of e.entries())
        if (!n.has(i[0])) return !1;
      return !0;
    }
    if (ArrayBuffer.isView(e) && ArrayBuffer.isView(n)) {
      if (r = e.length, r != n.length) return !1;
      for (i = r; i-- !== 0; )
        if (e[i] !== n[i]) return !1;
      return !0;
    }
    if (e.constructor === RegExp) return e.source === n.source && e.flags === n.flags;
    if (e.valueOf !== Object.prototype.valueOf) return e.valueOf() === n.valueOf();
    if (e.toString !== Object.prototype.toString) return e.toString() === n.toString();
    if (o = Object.keys(e), r = o.length, r !== Object.keys(n).length) return !1;
    for (i = r; i-- !== 0; )
      if (!Object.prototype.hasOwnProperty.call(n, o[i])) return !1;
    for (i = r; i-- !== 0; ) {
      var s = o[i];
      if (!t(e[s], n[s])) return !1;
    }
    return !0;
  }
  return e !== e && n !== n;
};
const Xd = /* @__PURE__ */ Do(Ud);
var go = /* @__PURE__ */ new Set();
function Yd() {
  return globalThis.SCRIPT_DEBUG === !0;
}
function kn(t) {
  if (Yd() && !go.has(t)) {
    console.warn(t);
    try {
      throw Error(t);
    } catch {
    }
    go.add(t);
  }
}
var ni = kt(
  /** @type {Record<string, any>} */
  {}
);
ni.displayName = "ComponentsContext";
var na = () => vt(ni);
function Kd({
  value: t
}) {
  const e = na(), n = F(t);
  return $s(() => {
    // Objects are equivalent.
    Xd(n.current, t) && // But not the same reference.
    n.current !== t && globalThis.SCRIPT_DEBUG === !0 && kn(`Please memoize your context: ${JSON.stringify(t)}`);
  }, [t]), jt(() => Gd(e ?? {}, t ?? {}, {
    isMergeableObject: Qa
  }), [e, t]);
}
var Zd = ({
  children: t,
  value: e
}) => {
  const n = Kd({
    value: e
  });
  return /* @__PURE__ */ C(ni.Provider, {
    value: n,
    children: t
  });
}, hh = la(Zd), qd = "data-wp-component", Jd = "data-wp-c16t", pe = "__contextSystemKey__";
function Qd(t) {
  return `components-${oc(t)}`;
}
var ra = Sd(Qd);
function tp(t, e) {
  return ia(t, e, {
    forwardsRef: !0
  });
}
function vh(t, e) {
  return ia(t, e);
}
function ia(t, e, n) {
  const r = n?.forwardsRef ? Ot(t) : t;
  typeof e > "u" && globalThis.SCRIPT_DEBUG === !0 && kn("contextConnect: Please provide a namespace");
  let i = r[pe] || [e];
  return Array.isArray(e) && (i = [...i, ...e]), typeof e == "string" && (i = [...i, e]), Object.assign(r, {
    [pe]: [...new Set(i)],
    displayName: e,
    selector: `.${ra(e)}`
  });
}
function yo(t) {
  if (!t)
    return [];
  let e = [];
  return t[pe] && (e = t[pe]), t.type && t.type[pe] && (e = t.type[pe]), e;
}
function gh(t, e) {
  return t ? typeof e == "string" ? yo(t).includes(e) : Array.isArray(e) ? e.some((n) => yo(t).includes(n)) : !1 : !1;
}
function ep(t) {
  return {
    [qd]: t
  };
}
function np() {
  return {
    [Jd]: !0
  };
}
function rp(t, e) {
  const n = na();
  typeof e > "u" && globalThis.SCRIPT_DEBUG === !0 && kn("useContextSystem: Please provide a namespace");
  const r = n?.[e] || {}, i = {
    ...np(),
    ...ep(e)
  }, {
    _overrides: o,
    ...s
  } = r, a = Object.entries(s).length ? Object.assign({}, s, t) : t, c = xd()(ra(e), t.className), l = typeof a.renderChildren == "function" ? a.renderChildren(a) : a.children;
  for (const f in a)
    i[f] = a[f];
  for (const f in o)
    i[f] = o[f];
  return l !== void 0 && (i.children = l), i.className = c, i;
}
var ip = {
  border: 0,
  clip: "rect(1px, 1px, 1px, 1px)",
  WebkitClipPath: "inset( 50% )",
  clipPath: "inset( 50% )",
  height: "1px",
  margin: "-1px",
  overflow: "hidden",
  padding: 0,
  position: "absolute",
  width: "1px",
  wordWrap: "normal",
  wordBreak: "normal"
}, op = /^((children|dangerouslySetInnerHTML|key|ref|autoFocus|defaultValue|defaultChecked|innerHTML|suppressContentEditableWarning|suppressHydrationWarning|valueLink|abbr|accept|acceptCharset|accessKey|action|allow|allowUserMedia|allowPaymentRequest|allowFullScreen|allowTransparency|alt|async|autoComplete|autoPlay|capture|cellPadding|cellSpacing|challenge|charSet|checked|cite|classID|className|cols|colSpan|content|contentEditable|contextMenu|controls|controlsList|coords|crossOrigin|data|dateTime|decoding|default|defer|dir|disabled|disablePictureInPicture|disableRemotePlayback|download|draggable|encType|enterKeyHint|fetchpriority|fetchPriority|form|formAction|formEncType|formMethod|formNoValidate|formTarget|frameBorder|headers|height|hidden|high|href|hrefLang|htmlFor|httpEquiv|id|inputMode|integrity|is|keyParams|keyType|kind|label|lang|list|loading|loop|low|marginHeight|marginWidth|max|maxLength|media|mediaGroup|method|min|minLength|multiple|muted|name|nonce|noValidate|open|optimum|pattern|placeholder|playsInline|popover|popoverTarget|popoverTargetAction|poster|preload|profile|radioGroup|readOnly|referrerPolicy|rel|required|reversed|role|rows|rowSpan|sandbox|scope|scoped|scrolling|seamless|selected|shape|size|sizes|slot|span|spellCheck|src|srcDoc|srcLang|srcSet|start|step|style|summary|tabIndex|target|title|translate|type|useMap|value|width|wmode|wrap|about|datatype|inlist|prefix|property|resource|typeof|vocab|autoCapitalize|autoCorrect|autoSave|color|incremental|fallback|inert|itemProp|itemScope|itemType|itemID|itemRef|on|option|results|security|unselectable|accentHeight|accumulate|additive|alignmentBaseline|allowReorder|alphabetic|amplitude|arabicForm|ascent|attributeName|attributeType|autoReverse|azimuth|baseFrequency|baselineShift|baseProfile|bbox|begin|bias|by|calcMode|capHeight|clip|clipPathUnits|clipPath|clipRule|colorInterpolation|colorInterpolationFilters|colorProfile|colorRendering|contentScriptType|contentStyleType|cursor|cx|cy|d|decelerate|descent|diffuseConstant|direction|display|divisor|dominantBaseline|dur|dx|dy|edgeMode|elevation|enableBackground|end|exponent|externalResourcesRequired|fill|fillOpacity|fillRule|filter|filterRes|filterUnits|floodColor|floodOpacity|focusable|fontFamily|fontSize|fontSizeAdjust|fontStretch|fontStyle|fontVariant|fontWeight|format|from|fr|fx|fy|g1|g2|glyphName|glyphOrientationHorizontal|glyphOrientationVertical|glyphRef|gradientTransform|gradientUnits|hanging|horizAdvX|horizOriginX|ideographic|imageRendering|in|in2|intercept|k|k1|k2|k3|k4|kernelMatrix|kernelUnitLength|kerning|keyPoints|keySplines|keyTimes|lengthAdjust|letterSpacing|lightingColor|limitingConeAngle|local|markerEnd|markerMid|markerStart|markerHeight|markerUnits|markerWidth|mask|maskContentUnits|maskUnits|mathematical|mode|numOctaves|offset|opacity|operator|order|orient|orientation|origin|overflow|overlinePosition|overlineThickness|panose1|paintOrder|pathLength|patternContentUnits|patternTransform|patternUnits|pointerEvents|points|pointsAtX|pointsAtY|pointsAtZ|preserveAlpha|preserveAspectRatio|primitiveUnits|r|radius|refX|refY|renderingIntent|repeatCount|repeatDur|requiredExtensions|requiredFeatures|restart|result|rotate|rx|ry|scale|seed|shapeRendering|slope|spacing|specularConstant|specularExponent|speed|spreadMethod|startOffset|stdDeviation|stemh|stemv|stitchTiles|stopColor|stopOpacity|strikethroughPosition|strikethroughThickness|string|stroke|strokeDasharray|strokeDashoffset|strokeLinecap|strokeLinejoin|strokeMiterlimit|strokeOpacity|strokeWidth|surfaceScale|systemLanguage|tableValues|targetX|targetY|textAnchor|textDecoration|textRendering|textLength|to|transform|u1|u2|underlinePosition|underlineThickness|unicode|unicodeBidi|unicodeRange|unitsPerEm|vAlphabetic|vHanging|vIdeographic|vMathematical|values|vectorEffect|version|vertAdvY|vertOriginX|vertOriginY|viewBox|viewTarget|visibility|widths|wordSpacing|writingMode|x|xHeight|x1|x2|xChannelSelector|xlinkActuate|xlinkArcrole|xlinkHref|xlinkRole|xlinkShow|xlinkTitle|xlinkType|xmlBase|xmlns|xmlnsXlink|xmlLang|xmlSpace|y|y1|y2|yChannelSelector|z|zoomAndPan|for|class|autofocus)|(([Dd][Aa][Tt][Aa]|[Aa][Rr][Ii][Aa]|x)-.*))$/, sp = /* @__PURE__ */ Us(
  function(t) {
    return op.test(t) || t.charCodeAt(0) === 111 && t.charCodeAt(1) === 110 && t.charCodeAt(2) < 91;
  }
  /* Z+1 */
), ap = sp, cp = function(e) {
  return e !== "theme";
}, bo = function(e) {
  return typeof e == "string" && // 96 is one less than the char code
  // for "a" so this is checking that
  // it's a lowercase character
  e.charCodeAt(0) > 96 ? ap : cp;
}, wo = function(e, n, r) {
  var i;
  if (n) {
    var o = n.shouldForwardProp;
    i = e.__emotion_forwardProp && o ? function(s) {
      return e.__emotion_forwardProp(s) && o(s);
    } : o;
  }
  return typeof i != "function" && r && (i = e.__emotion_forwardProp), i;
}, up = function(e) {
  var n = e.cache, r = e.serialized, i = e.isStringTag;
  return ti(n, r, i), Ks(function() {
    return Ln(n, r, i);
  }), null;
}, lp = function t(e, n) {
  var r = e.__emotion_real === e, i = r && e.__emotion_base || e, o, s;
  n !== void 0 && (o = n.label, s = n.target);
  var a = wo(e, n, r), u = a || bo(i), c = !u("as");
  return function() {
    var l = arguments, f = r && e.__emotion_styles !== void 0 ? e.__emotion_styles.slice(0) : [];
    if (o !== void 0 && f.push("label:" + o + ";"), l[0] == null || l[0].raw === void 0)
      f.push.apply(f, l);
    else {
      var m = l[0];
      f.push(m[0]);
      for (var h = l.length, p = 1; p < h; p++)
        f.push(l[p], m[p]);
    }
    var d = Zs(function(w, x, v) {
      var b = c && w.as || i, g = "", y = [], E = w;
      if (w.theme == null) {
        E = {};
        for (var S in w)
          E[S] = w[S];
        E.theme = W.useContext(qs);
      }
      typeof w.className == "string" ? g = Fn(x.registered, y, w.className) : w.className != null && (g = w.className + " ");
      var A = Ne(f.concat(y), x.registered, E);
      g += x.key + "-" + A.name, s !== void 0 && (g += " " + s);
      var _ = c && a === void 0 ? bo(b) : u, T = {};
      for (var O in w)
        c && O === "as" || _(O) && (T[O] = w[O]);
      return T.className = g, v && (T.ref = v), /* @__PURE__ */ W.createElement(W.Fragment, null, /* @__PURE__ */ W.createElement(up, {
        cache: x,
        serialized: A,
        isStringTag: typeof b == "string"
      }), /* @__PURE__ */ W.createElement(b, T));
    });
    return d.displayName = o !== void 0 ? o : "Styled(" + (typeof i == "string" ? i : i.displayName || i.name || "Component") + ")", d.defaultProps = e.defaultProps, d.__emotion_real = d, d.__emotion_base = i, d.__emotion_styles = f, d.__emotion_forwardProp = a, Object.defineProperty(d, "toString", {
      value: function() {
        return "." + s;
      }
    }), d.withComponent = function(w, x) {
      var v = t(w, mr({}, n, x, {
        shouldForwardProp: wo(d, x, !0)
      }));
      return v.apply(void 0, f);
    }, d;
  };
}, fp = /* @__PURE__ */ lp("div", process.env.NODE_ENV === "production" ? {
  target: "e19lxcc00"
} : {
  target: "e19lxcc00",
  label: "PolymorphicDiv"
})(process.env.NODE_ENV === "production" ? "" : "/*# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbImNvbXBvbmVudC50c3giXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBZWlDIiwiZmlsZSI6ImNvbXBvbmVudC50c3giLCJzb3VyY2VzQ29udGVudCI6WyIvKipcbiAqIEV4dGVybmFsIGRlcGVuZGVuY2llc1xuICovXG5pbXBvcnQgc3R5bGVkIGZyb20gJ0BlbW90aW9uL3N0eWxlZCc7XG5cbi8qKlxuICogV29yZFByZXNzIGRlcGVuZGVuY2llc1xuICovXG5pbXBvcnQgeyBmb3J3YXJkUmVmIH0gZnJvbSAnQHdvcmRwcmVzcy9lbGVtZW50JztcblxuLyoqXG4gKiBJbnRlcm5hbCBkZXBlbmRlbmNpZXNcbiAqL1xuaW1wb3J0IHR5cGUgeyBXb3JkUHJlc3NDb21wb25lbnRQcm9wcyB9IGZyb20gJy4uL2NvbnRleHQnO1xuXG5jb25zdCBQb2x5bW9ycGhpY0RpdiA9IHN0eWxlZC5kaXZgYDtcblxuZnVuY3Rpb24gVW5mb3J3YXJkZWRWaWV3PCBUIGV4dGVuZHMgUmVhY3QuRWxlbWVudFR5cGUgPSAnZGl2JyA+KFxuXHR7IGFzLCAuLi5yZXN0UHJvcHMgfTogV29yZFByZXNzQ29tcG9uZW50UHJvcHM8IHt9LCBUID4sXG5cdHJlZjogUmVhY3QuRm9yd2FyZGVkUmVmPCBhbnkgPlxuKSB7XG5cdHJldHVybiA8UG9seW1vcnBoaWNEaXYgYXM9eyBhcyB9IHJlZj17IHJlZiB9IHsgLi4ucmVzdFByb3BzIH0gLz47XG59XG5cbi8qKlxuICogYFZpZXdgIGlzIGEgY29yZSBjb21wb25lbnQgdGhhdCByZW5kZXJzIGV2ZXJ5dGhpbmcgaW4gdGhlIGxpYnJhcnkuXG4gKiBJdCBpcyB0aGUgcHJpbmNpcGxlIGNvbXBvbmVudCBpbiB0aGUgZW50aXJlIGxpYnJhcnkuXG4gKlxuICogYGBganN4XG4gKiBpbXBvcnQgeyBWaWV3IH0gZnJvbSBgQHdvcmRwcmVzcy9jb21wb25lbnRzYDtcbiAqXG4gKiBmdW5jdGlvbiBFeGFtcGxlKCkge1xuICogXHRyZXR1cm4gKFxuICogXHRcdDxWaWV3PlxuICogXHRcdFx0IENvZGUgaXMgUG9ldHJ5XG4gKiBcdFx0PC9WaWV3PlxuICogXHQpO1xuICogfVxuICogYGBgXG4gKi9cbmV4cG9ydCBjb25zdCBWaWV3ID0gT2JqZWN0LmFzc2lnbiggZm9yd2FyZFJlZiggVW5mb3J3YXJkZWRWaWV3ICksIHtcblx0c2VsZWN0b3I6ICcuY29tcG9uZW50cy12aWV3Jyxcbn0gKTtcblxuZXhwb3J0IGRlZmF1bHQgVmlldztcbiJdfQ== */");
function dp({
  as: t,
  ...e
}, n) {
  return /* @__PURE__ */ C(fp, {
    as: t,
    ref: n,
    ...e
  });
}
var pp = Object.assign(Ot(dp), {
  selector: ".components-view"
}), oa = pp;
function mp(t, e) {
  const {
    style: n,
    ...r
  } = rp(t, "VisuallyHidden");
  return /* @__PURE__ */ C(oa, {
    ref: e,
    ...r,
    style: {
      ...ip,
      ...n || {}
    }
  });
}
var hp = tp(mp, "VisuallyHidden"), vp = hp, gp = /* @__PURE__ */ C(ke, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ C(Ho, { d: "M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z" }) }), yp = /* @__PURE__ */ C(ke, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ C(Ho, { d: "M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z" }) });
function bp({
  icon: t,
  className: e,
  size: n = 20,
  style: r = {},
  ...i
}) {
  const o = ["dashicon", "dashicons", "dashicons-" + t, e].filter(Boolean).join(" "), a = {
    ...// using `!=` to catch both 20 and "20"
    // eslint-disable-next-line eqeqeq
    n != 20 ? {
      fontSize: `${n}px`,
      width: `${n}px`,
      height: `${n}px`
    } : {},
    ...r
  };
  return /* @__PURE__ */ C("span", {
    className: o,
    style: a,
    ...i
  });
}
var xo = bp;
function wp({
  icon: t = null,
  size: e = typeof t == "string" ? 20 : 24,
  ...n
}) {
  if (typeof t == "string")
    return /* @__PURE__ */ C(xo, {
      icon: t,
      size: e,
      ...n
    });
  if (Fe(t) && xo === t.type)
    return Le(t, {
      ...n
    });
  if (typeof t == "function")
    return Se(t, {
      size: e,
      ...n
    });
  if (t && (t.type === "svg" || t.type === ke)) {
    const r = {
      ...t.props,
      width: e,
      height: e,
      ...n
    };
    return /* @__PURE__ */ C(ke, {
      ...r
    });
  }
  return Fe(t) ? Le(t, {
    // @ts-ignore Just forwarding the size prop along
    size: e,
    width: e,
    height: e,
    ...n
  }) : t;
}
var En = wp, xp = ["onMouseDown", "onClick"];
function Sp({
  __experimentalIsFocusable: t,
  isDefault: e,
  isPrimary: n,
  isSecondary: r,
  isTertiary: i,
  isLink: o,
  isPressed: s,
  isSmall: a,
  size: u,
  variant: c,
  describedBy: l,
  ...f
}) {
  let m = u, h = c;
  const p = {
    accessibleWhenDisabled: t,
    // @todo Mark `isPressed` as deprecated
    "aria-pressed": s,
    description: l
  };
  return a && (m ??= "small"), n && (h ??= "primary"), i && (h ??= "tertiary"), r && (h ??= "secondary"), e && (Wo("wp.components.Button `isDefault` prop", {
    since: "5.4",
    alternative: 'variant="secondary"'
  }), h ??= "secondary"), o && (h ??= "link"), {
    ...p,
    ...f,
    size: m,
    variant: h
  };
}
function Ep(t, e) {
  const {
    __next40pxDefaultSize: n,
    accessibleWhenDisabled: r,
    isBusy: i,
    isDestructive: o,
    className: s,
    disabled: a,
    icon: u,
    iconPosition: c = "left",
    iconSize: l,
    showTooltip: f,
    tooltipPosition: m,
    shortcut: h,
    label: p,
    children: d,
    size: w = "default",
    text: x,
    variant: v,
    description: b,
    ...g
  } = Sp(t), {
    href: y,
    target: E,
    "aria-checked": S,
    "aria-pressed": A,
    "aria-selected": _,
    ...T
  } = "href" in g ? g : {
    href: void 0,
    target: void 0,
    ...g
  }, O = jo(ri, "components-button__description"), M = typeof d == "string" && !!d || Array.isArray(d) && d?.[0] && d[0] !== null && // Tooltip should not considered as a child
  d?.[0]?.props?.className !== "components-tooltip", I = Pn("components-button", s, {
    "is-next-40px-default-size": n,
    "is-secondary": v === "secondary",
    "is-primary": v === "primary",
    "is-small": w === "small",
    "is-compact": w === "compact",
    "is-tertiary": v === "tertiary",
    "is-pressed": [!0, "true", "mixed"].includes(A),
    "is-pressed-mixed": A === "mixed",
    "is-busy": i,
    "is-link": v === "link",
    "is-destructive": o,
    "has-text": !!u && (M || x),
    "has-icon": !!u,
    "has-icon-right": c === "right"
  }), k = a && !r, P = y !== void 0 && !a ? "a" : "button", N = P === "button" ? {
    type: "button",
    disabled: k,
    "aria-checked": S,
    "aria-pressed": A,
    "aria-selected": _
  } : {}, L = P === "a" ? {
    href: y,
    target: E
  } : {}, z = {};
  if (a && r) {
    N["aria-disabled"] = !0, L["aria-disabled"] = !0;
    for (const Ut of xp)
      z[Ut] = (lt) => {
        lt && (lt.stopPropagation(), lt.preventDefault());
      };
  }
  const at = !k && // An explicit tooltip is passed or...
  (f && !!p || // There's a shortcut or...
  !!h || // There's a label and...
  !!p && // The children are empty and...
  !d?.length && // The tooltip is not explicitly disabled.
  f !== !1), yt = b ? O : void 0, tt = T["aria-describedby"] || yt, _t = {
    className: I,
    "aria-label": T["aria-label"] || p,
    "aria-describedby": tt,
    ref: e
  }, Oe = /* @__PURE__ */ Et(St, {
    children: [u && c === "left" && /* @__PURE__ */ C(En, {
      icon: u,
      size: l
    }), x && /* @__PURE__ */ C(St, {
      children: x
    }), d, u && c === "right" && /* @__PURE__ */ C(En, {
      icon: u,
      size: l
    })]
  }), Gt = P === "a" ? /* @__PURE__ */ C("a", {
    ...L,
    ...T,
    ...z,
    ..._t,
    children: Oe
  }) : /* @__PURE__ */ C("button", {
    ...N,
    ...T,
    ...z,
    ..._t,
    children: Oe
  }), wt = at ? {
    text: d?.length && b ? b : p,
    shortcut: h,
    placement: m && // Convert legacy `position` values to be used with the new `placement` prop
    Qs(m)
  } : {};
  return /* @__PURE__ */ Et(St, {
    children: [/* @__PURE__ */ C(Rd, {
      ...wt,
      children: Gt
    }), b && /* @__PURE__ */ C(vp, {
      children: /* @__PURE__ */ C("span", {
        id: yt,
        children: b
      })
    })]
  });
}
var ri = Ot(Ep);
ri.displayName = "Button";
var Ap = ri, Pp = {
  slots: vn(),
  fills: vn(),
  registerSlot: () => {
    globalThis.SCRIPT_DEBUG === !0 && kn("Components must be wrapped within `SlotFillProvider`. See https://developer.wordpress.org/block-editor/components/slot-fill/");
  },
  unregisterSlot: () => {
  },
  updateSlot: () => {
  },
  registerFill: () => {
  },
  unregisterFill: () => {
  },
  updateFill: () => {
  },
  // This helps the provider know if it's using the default context value or not.
  isDefault: !0
}, sa = kt(Pp);
sa.displayName = "SlotFillContext";
var qe = sa;
let ln;
const Cp = new Uint8Array(16);
function Op() {
  if (!ln && (ln = typeof crypto < "u" && crypto.getRandomValues && crypto.getRandomValues.bind(crypto), !ln))
    throw new Error("crypto.getRandomValues() not supported. See https://github.com/uuidjs/uuid#getrandomvalues-not-supported");
  return ln(Cp);
}
const et = [];
for (let t = 0; t < 256; ++t)
  et.push((t + 256).toString(16).slice(1));
function _p(t, e = 0) {
  return et[t[e + 0]] + et[t[e + 1]] + et[t[e + 2]] + et[t[e + 3]] + "-" + et[t[e + 4]] + et[t[e + 5]] + "-" + et[t[e + 6]] + et[t[e + 7]] + "-" + et[t[e + 8]] + et[t[e + 9]] + "-" + et[t[e + 10]] + et[t[e + 11]] + et[t[e + 12]] + et[t[e + 13]] + et[t[e + 14]] + et[t[e + 15]];
}
const Tp = typeof crypto < "u" && crypto.randomUUID && crypto.randomUUID.bind(crypto), So = {
  randomUUID: Tp
};
function Eo(t, e, n) {
  if (So.randomUUID && !t)
    return So.randomUUID();
  t = t || {};
  const r = t.random || (t.rng || Op)();
  return r[6] = r[6] & 15 | 64, r[8] = r[8] & 63 | 128, _p(r);
}
var Ao = /* @__PURE__ */ new Set(), qn = /* @__PURE__ */ new WeakMap(), Rp = (t) => {
  if (qn.has(t))
    return qn.get(t);
  let e = Eo().replace(/[0-9]/g, "");
  for (; Ao.has(e); )
    e = Eo().replace(/[0-9]/g, "");
  Ao.add(e);
  const n = Qr({
    container: t,
    key: e
  });
  return qn.set(t, n), n;
};
function Dp(t) {
  const {
    children: e,
    document: n
  } = t;
  if (!n)
    return null;
  const r = Rp(n.head);
  return /* @__PURE__ */ C(dd, {
    value: r,
    children: e
  });
}
var Ip = Dp;
function Np({
  name: t,
  children: e
}) {
  const n = vt(qe), r = F({}), i = F(e);
  Mt(() => {
    i.current = e;
  }, [e]), Mt(() => {
    const u = r.current;
    return n.registerFill(t, {
      instance: u,
      children: i.current
    }), () => n.unregisterFill(t, u);
  }, [n, t]), Mt(() => {
    n.updateFill(t, {
      instance: r.current,
      children: i.current
    });
  });
  const o = ir(n.slots, t);
  if (!o || o.type === "children")
    return null;
  const s = o.ref.current;
  if (!s)
    return null;
  const a = typeof e == "function" ? e(o.fillProps ?? {}) : e;
  return Qn(/* @__PURE__ */ C(Ip, {
    document: s.ownerDocument,
    children: a
  }), s);
}
function Po(t) {
  return typeof t == "function";
}
function Mp(t) {
  return gr.map(t, (e, n) => {
    if (!e || typeof e == "string")
      return e;
    let r = n;
    return typeof e == "object" && "key" in e && e?.key && (r = e.key), Le(e, {
      key: r
    });
  });
}
function Fp(t) {
  const {
    name: e,
    children: n,
    fillProps: r = {}
  } = t, i = vt(qe), o = F({});
  Mt(() => {
    const c = o.current;
    return i.registerSlot(e, {
      type: "children",
      instance: c
    }), () => i.unregisterSlot(e, c);
  }, [i, e]);
  let s = ir(i.fills, e) ?? [];
  const a = ir(i.slots, e);
  (!a || a.instance !== o.current) && (s = []);
  const u = s.map((c) => {
    const l = Po(c.children) ? c.children(r) : c.children;
    return Mp(l);
  }).filter(
    // In some cases fills are rendered only when some conditions apply.
    // This ensures that we only use non-empty fills when rendering, i.e.,
    // it allows us to render wrappers only when the fills are actually present.
    (c) => !Ja(c)
  );
  return /* @__PURE__ */ C(St, {
    children: Po(n) ? n(u) : u
  });
}
var Lp = Fp;
function kp(t, e) {
  const {
    name: n,
    fillProps: r = {},
    as: i,
    // `children` is not allowed. However, if it is passed,
    // it will be displayed as is, so remove `children`.
    children: o,
    ...s
  } = t, a = vt(qe), u = F({}), c = F(null), l = F(r);
  return Mt(() => {
    l.current = r;
  }, [r]), Mt(() => {
    const f = u.current;
    return a.registerSlot(n, {
      type: "portal",
      instance: f,
      ref: c,
      fillProps: l.current
    }), () => a.unregisterSlot(n, f);
  }, [a, n]), Mt(() => {
    a.updateSlot(n, {
      type: "portal",
      instance: u.current,
      ref: c,
      fillProps: l.current
    });
  }), /* @__PURE__ */ C(oa, {
    as: i,
    ref: zo([e, c]),
    ...s
  });
}
var Vp = Ot(kp);
function $p() {
  const t = vn(), e = vn();
  function n(u, c) {
    t.set(u, c);
  }
  function r(u, c) {
    const l = t.get(u);
    !l || l.instance !== c || t.delete(u);
  }
  function i(u, c) {
    if (c.type !== "portal")
      return;
    const l = t.get(u);
    l && l.type === "portal" && l.instance === c.instance && (cc(l.fillProps, c.fillProps) || t.set(u, c));
  }
  function o(u, c) {
    e.set(u, [...e.get(u) || [], c]);
  }
  function s(u, c) {
    const l = e.get(u);
    l && e.set(u, l.filter((f) => f.instance !== c));
  }
  function a(u, c) {
    const l = e.get(u);
    if (!l)
      return;
    const f = l.find((m) => m.instance === c.instance);
    f && f.children !== c.children && e.set(u, l.map((m) => m.instance === c.instance ? c : m));
  }
  return {
    slots: t,
    fills: e,
    registerSlot: n,
    unregisterSlot: r,
    updateSlot: i,
    registerFill: o,
    unregisterFill: s,
    updateFill: a
  };
}
function Bp({
  children: t
}) {
  const [e] = Y($p);
  return /* @__PURE__ */ C(qe.Provider, {
    value: e,
    children: t
  });
}
var Hp = Bp, aa = Ot((t, e) => {
  const {
    bubblesVirtually: n,
    ...r
  } = t;
  return n ? /* @__PURE__ */ C(Vp, {
    ...r,
    ref: e
  }) : /* @__PURE__ */ C(Lp, {
    ...r
  });
});
aa.displayName = "Slot";
function Wp({
  children: t,
  passthrough: e = !1
}) {
  return !vt(qe).isDefault && e ? /* @__PURE__ */ C(St, {
    children: t
  }) : /* @__PURE__ */ C(Hp, {
    children: t
  });
}
Wp.displayName = "SlotFillProvider";
function jp(t) {
  const e = typeof t == "symbol" ? t.description : t, n = (i) => /* @__PURE__ */ C(Np, {
    name: t,
    ...i
  });
  n.displayName = `${e}Fill`;
  const r = Ot((i, o) => /* @__PURE__ */ C(aa, {
    name: t,
    ref: o,
    ...i
  }));
  return r.displayName = `${e}Slot`, r.__unstableName = t, {
    name: t,
    Fill: n,
    Slot: r
  };
}
var zp = () => {
};
function Gp(t, e) {
  const {
    buttonProps: n = {},
    children: r,
    className: i,
    icon: o,
    initialOpen: s,
    onToggle: a = zp,
    opened: u,
    title: c,
    scrollAfterOpen: l = !0
  } = t, [f, m] = _f(u, {
    initial: s === void 0 ? !0 : s,
    fallback: !1
  }), h = F(null), p = mc() ? "auto" : "smooth", d = (v) => {
    v.preventDefault();
    const b = !f;
    m(b), a(b);
  }, w = F(void 0);
  w.current = l, $s(() => {
    f && w.current && h.current?.scrollIntoView && h.current.scrollIntoView({
      inline: "nearest",
      block: "nearest",
      behavior: p
    });
  }, [f, p]);
  const x = Pn("components-panel__body", i, {
    "is-opened": f
  });
  return /* @__PURE__ */ Et("div", {
    className: x,
    ref: zo([h, e]),
    children: [/* @__PURE__ */ C(Up, {
      icon: o,
      isOpened: !!f,
      onClick: d,
      title: c,
      ...n
    }), typeof r == "function" ? r({
      opened: !!f
    }) : f && r]
  });
}
var Up = Ot(({
  isOpened: t,
  icon: e,
  title: n,
  ...r
}, i) => n ? /* @__PURE__ */ C("h2", {
  className: "components-panel__body-title",
  children: /* @__PURE__ */ Et(Ap, {
    __next40pxDefaultSize: !0,
    className: "components-panel__body-toggle",
    "aria-expanded": t,
    ref: i,
    ...r,
    children: [/* @__PURE__ */ C("span", {
      "aria-hidden": "true",
      children: /* @__PURE__ */ C(En, {
        className: "components-panel__arrow",
        icon: t ? yp : gp
      })
    }), n, e && /* @__PURE__ */ C(En, {
      icon: e,
      className: "components-panel__icon",
      size: 20
    })]
  })
}) : null), ca = Ot(Gp);
ca.displayName = "PanelBody";
var Xp = ca;
const Yp = "ApVisualEditorDocumentPanel", { Slot: Kp, Fill: Zp } = jp(Yp), qp = "ap.visualEditor.documentPanels";
function yh(t) {
  const { name: e, title: n, initialOpen: r = !1, className: i, children: o } = t;
  return /* @__PURE__ */ C(Zp, { children: /* @__PURE__ */ C(
    Xp,
    {
      title: n,
      initialOpen: r,
      className: i,
      children: /* @__PURE__ */ C("div", { "data-panel-name": e, children: o })
    }
  ) });
}
function bh() {
  return /* @__PURE__ */ C(Kp, {});
}
function wh() {
  const t = nr(
    qp,
    []
  );
  if (!Array.isArray(t))
    return [];
  const n = t.filter(
    (i) => i !== null && typeof i == "object" && typeof i.id == "string" && typeof i.title == "string" && typeof i.render == "function"
  ).map((i, o) => ({ panel: i, index: o })).sort((i, o) => {
    const s = i.panel.order ?? 100, a = o.panel.order ?? 100;
    return s !== a ? s - a : i.index - o.index;
  }).map(({ panel: i }) => i), r = /* @__PURE__ */ new Map();
  for (const i of n)
    r.has(i.id) && r.delete(i.id), r.set(i.id, i);
  return Array.from(r.values());
}
Ca();
const Jp = "[data-ap-visual-editor]", Me = Symbol("ap-visual-editor-root");
function Ie(t, e) {
  if (t === void 0)
    return null;
  const n = t.trim();
  if (n === "")
    return null;
  try {
    return JSON.parse(n);
  } catch (r) {
    return console.warn(
      `visual-editor: could not parse ${e} dataset attribute as JSON.`,
      r
    ), null;
  }
}
function Qp(t) {
  const e = t.dataset.apiBase?.trim(), n = t.dataset.resource?.trim(), r = t.dataset.id?.trim();
  if (!e || !n || !r)
    return null;
  const i = t.dataset.title?.trim(), o = t.dataset.slug?.trim(), s = t.dataset.status?.trim(), a = t.dataset.excerpt, u = t.dataset.authorId?.trim(), c = t.dataset.commentsOpen?.trim(), l = t.dataset.previewUrl?.trim(), f = t.dataset.parent?.trim(), m = t.dataset.menuOrder?.trim(), h = t.dataset.template?.trim(), p = t.dataset.createdAt?.trim(), d = t.dataset.updatedAt?.trim(), w = Co(
    t.dataset.categories,
    "data-categories"
  ), x = Co(
    t.dataset.tags,
    "data-tags"
  );
  let v;
  f === void 0 ? v = void 0 : f === "" ? v = null : v = Oo(f) ?? void 0;
  const b = Oo(m), g = Ie(
    t.dataset.featuredImage,
    "data-featured-image"
  ), y = Ie(
    t.dataset.authorOptions,
    "data-author-options"
  ), E = Array.isArray(
    y
  ) ? y : null, S = Ie(
    t.dataset.supports,
    "data-supports"
  ), A = Ie(t.dataset.breakpoints, "data-breakpoints"), _ = Array.isArray(A) ? { breakpoints: A } : null, T = tm(u, E);
  return {
    apiBase: e,
    resource: n,
    id: r,
    ...i ? { initialTitle: i } : {},
    ...o ? { initialSlug: o } : {},
    ...s ? { initialStatus: s } : {},
    ...a !== void 0 ? { initialExcerpt: a } : {},
    ...T !== void 0 ? { initialAuthorId: T } : {},
    ...c !== void 0 ? { initialCommentsOpen: c === "true" } : {},
    ...g !== null ? { initialFeaturedImage: g } : {},
    ...E !== null ? { authorOptions: E } : {},
    ...S !== null ? { supports: S } : {},
    ...w !== null ? { initialCategories: w } : {},
    ...x !== null ? { initialTags: x } : {},
    ...v !== void 0 ? { initialParent: v } : {},
    ...b !== null ? { initialMenuOrder: b } : {},
    ...h ? { initialTemplate: h } : {},
    ...p ? { initialCreatedAt: p } : {},
    ...d ? { initialUpdatedAt: d } : {},
    ..._ !== null ? { breakpoints: _ } : {},
    previewUrl: l ?? null
  };
}
function Co(t, e) {
  const n = Ie(t, e);
  if (!Array.isArray(n))
    return null;
  const r = [];
  for (const i of n) {
    if (typeof i == "number" && Number.isInteger(i) && i > 0) {
      r.push(i);
      continue;
    }
    typeof i == "string" && i.trim() !== "" && /^[1-9]\d*$/.test(i.trim()) && r.push(Number.parseInt(i.trim(), 10));
  }
  return Array.from(new Set(r));
}
function Oo(t) {
  if (t === void 0)
    return null;
  const e = t.trim();
  if (e === "" || !/^-?\d+$/.test(e))
    return null;
  const n = Number.parseInt(e, 10);
  return Number.isFinite(n) ? n : null;
}
function tm(t, e) {
  if (t === void 0 || t === "")
    return;
  if (e !== null && e.length > 0) {
    const r = e.find(
      (i) => String(i.value) === t || i.value === Number(t)
    );
    if (r !== void 0)
      return r.value;
  }
  const n = Number(t);
  return !Number.isNaN(n) && /^-?\d+(\.\d+)?$/.test(t) ? n : t;
}
function em(t, e) {
  const n = t;
  if (n[Me])
    return {
      ready: Promise.resolve(),
      unmount: () => Jn(n)
    };
  const r = tr(n);
  return n[Me] = r, {
    ready: import("./editor-app-BEgqJ-cK.js").then(
      ({ EditorApp: o }) => {
        n[Me] === r && r.render(Se(o, e));
      },
      (o) => {
        throw console.error("visual-editor: failed to load editor app.", o), Jn(n), o;
      }
    ),
    unmount: () => Jn(n)
  };
}
function Jn(t) {
  const e = t[Me];
  e !== void 0 && (delete t[Me], e.unmount());
}
async function nm(t) {
  const e = Qp(t);
  if (e === null) {
    console.error(
      "visual-editor: mount point is missing data-api-base, data-resource, or data-id.",
      t
    );
    return;
  }
  try {
    await em(t, e).ready;
  } catch {
  }
}
function An(t = document) {
  const e = t.querySelectorAll(Jp);
  return Promise.all(Array.from(e).map((n) => nm(n)));
}
typeof window < "u" && (window.ApVisualEditorBoot = An, window.ApVisualEditor = {
  boot: An,
  registerArtisanpackMediaBridge: Fa,
  registerMediaBridge: Lo
});
typeof document < "u" && (document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", () => {
  An();
}) : An());
export {
  Lr as $,
  Um as A,
  Ci as B,
  Qm as C,
  Tr as D,
  nh as E,
  oh as F,
  Xm as G,
  Hm as H,
  Xo as I,
  he as J,
  jm as K,
  Wm as L,
  xt as M,
  Tc as N,
  Jt as O,
  $ as P,
  th as Q,
  U as R,
  qm as S,
  wr as T,
  Ni as U,
  Qt as V,
  Jm as W,
  rh as X,
  vu as Y,
  Cn as Z,
  Ga as _,
  hn as a,
  Pn as a$,
  fs as a0,
  gu as a1,
  rs as a2,
  rt as a3,
  Yt as a4,
  Hn as a5,
  ht as a6,
  ts as a7,
  ou as a8,
  Gm as a9,
  ff as aA,
  ds as aB,
  yu as aC,
  Ls as aD,
  Zm as aE,
  gn as aF,
  yn as aG,
  ks as aH,
  $m as aI,
  Nc as aJ,
  km as aK,
  ih as aL,
  df as aM,
  hc as aN,
  pf as aO,
  _m as aP,
  Yr as aQ,
  bf as aR,
  wf as aS,
  Af as aT,
  Ne as aU,
  Js as aV,
  lh as aW,
  uh as aX,
  Sd as aY,
  Rd as aZ,
  vp as a_,
  qo as aa,
  $t as ab,
  Ht as ac,
  Dr as ad,
  ns as ae,
  es as af,
  ls as ag,
  Mc as ah,
  ss as ai,
  Mr as aj,
  _c as ak,
  sh as al,
  Fc as am,
  Vm as an,
  Jo as ao,
  jc as ap,
  Z as aq,
  Bt as ar,
  Km as as,
  Ym as at,
  zm as au,
  eh as av,
  as as aw,
  On as ax,
  Ms as ay,
  lf as az,
  cc as b,
  er as b$,
  ke as b0,
  Lm as b1,
  rp as b2,
  xd as b3,
  Wo as b4,
  tp as b5,
  oa as b6,
  Ho as b7,
  Fm as b8,
  Kr as b9,
  Om as bA,
  En as bB,
  gp as bC,
  _f as bD,
  ri as bE,
  Cm as bF,
  kn as bG,
  Ip as bH,
  fh as bI,
  am as bJ,
  Xd as bK,
  $s as bL,
  yf as bM,
  cm as bN,
  um as bO,
  nr as bP,
  Mm as bQ,
  ni as bR,
  Eo as bS,
  Ea as bT,
  dm as bU,
  vn as bV,
  Wp as bW,
  jp as bX,
  yp as bY,
  sc as bZ,
  Xp as b_,
  gh as ba,
  lp as bb,
  hh as bc,
  vh as bd,
  Ap as be,
  ch as bf,
  ah as bg,
  Do as bh,
  Wl as bi,
  zl as bj,
  qe as bk,
  ir as bl,
  Hl as bm,
  aa as bn,
  Vl as bo,
  Bl as bp,
  $l as bq,
  jl as br,
  ph as bs,
  mc as bt,
  dh as bu,
  Dp as bv,
  mh as bw,
  kl as bx,
  Np as by,
  Qs as bz,
  zo as c,
  zt as c0,
  Gd as c1,
  wh as c2,
  bh as c3,
  Nm as c4,
  Im as c5,
  Dm as c6,
  Rm as c7,
  Tm as c8,
  Na as c9,
  qp as ca,
  yh as cb,
  An as cc,
  em as cd,
  tm as ce,
  Co as cf,
  Oo as cg,
  Fa as ch,
  Lo as ci,
  dc as d,
  sm as e,
  Ge as f,
  Pm as g,
  Sr as h,
  Qa as i,
  K as j,
  gt as k,
  Zo as l,
  Oa as m,
  rc as n,
  wc as o,
  oc as p,
  te as q,
  Uo as r,
  st as s,
  ut as t,
  jo as u,
  Bm as v,
  Go as w,
  Lc as x,
  Q as y,
  Pi as z
};
//# sourceMappingURL=main-ClCPpBg2.js.map
