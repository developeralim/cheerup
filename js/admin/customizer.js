(function(modules) {
    var installedModules = {};
    function __webpack_require__(moduleId) {
        if (installedModules[moduleId]) {
            return installedModules[moduleId].exports;
        }
        var module = installedModules[moduleId] = {
            i: moduleId,
            l: false,
            exports: {}
        };
        modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
        module.l = true;
        return module.exports;
    }
    __webpack_require__.m = modules;
    __webpack_require__.c = installedModules;
    __webpack_require__.d = function(exports, name, getter) {
        if (!__webpack_require__.o(exports, name)) {
            Object.defineProperty(exports, name, {
                enumerable: true,
                get: getter
            });
        }
    };
    __webpack_require__.r = function(exports) {
        if (typeof Symbol !== "undefined" && Symbol.toStringTag) {
            Object.defineProperty(exports, Symbol.toStringTag, {
                value: "Module"
            });
        }
        Object.defineProperty(exports, "__esModule", {
            value: true
        });
    };
    __webpack_require__.t = function(value, mode) {
        if (mode & 1) value = __webpack_require__(value);
        if (mode & 8) return value;
        if (mode & 4 && typeof value === "object" && value && value.__esModule) return value;
        var ns = Object.create(null);
        __webpack_require__.r(ns);
        Object.defineProperty(ns, "default", {
            enumerable: true,
            value: value
        });
        if (mode & 2 && typeof value != "string") for (var key in value) __webpack_require__.d(ns, key, function(key) {
            return value[key];
        }.bind(null, key));
        return ns;
    };
    __webpack_require__.n = function(module) {
        var getter = module && module.__esModule ? function getDefault() {
            return module["default"];
        } : function getModuleExports() {
            return module;
        };
        __webpack_require__.d(getter, "a", getter);
        return getter;
    };
    __webpack_require__.o = function(object, property) {
        return Object.prototype.hasOwnProperty.call(object, property);
    };
    __webpack_require__.p = "";
    return __webpack_require__(__webpack_require__.s = 0);
})([ function(module, exports) {
    (function($, api, _) {
        "use strict";
        const optionsKey = Bunyad_CZ_Data.settingPrefix;
        const controlPrefix = Bunyad_CZ_Data.controlPrefix;
        $(document).on("input change", "#customize-control-bunyad_home_subscribe_url input", (function() {
            var code = $(this).val(), match = code.match(/action=\"([^\"]+)\"/);
            if (match) {
                $(this).val(match[1]);
            }
        }));
        api("cheerup_theme_options[post_grid_style]", (function(setting) {
            setting.bind((function(value, old) {
                if (value == "grid-b") {
                    api.control(controlPrefix + "post_grid_read_more").setting.set(1);
                }
            }));
        }));
        const syncNavAndTopbar = function() {
            const headerLayout = api(optionsKey + "[header_layout]").get();
            if ([ "nav-below", "nav-below-b", "compact", "simple", "simple-boxed" ].includes(headerLayout)) {
                return;
            }
            const current = api(optionsKey + "[topbar_style]").get();
            api(optionsKey + "[nav_style]").set(current);
        };
        api(optionsKey + "[topbar_style]", setting => {
            setting.bind(syncNavAndTopbar);
        });
        api(optionsKey + "[header_layout]", setting => {
            setting.bind((value, old) => {
                syncNavAndTopbar();
                if ([ "simple", "simple-boxed", "compact" ].includes(value)) {
                    api(optionsKey + "[nav_style]").set("light");
                }
                if (value === "compact") {
                    api(optionsKey + "[topbar_style]").set("dark");
                }
            });
        });
        api(optionsKey + "[read_more_style]", setting => {
            const affected = [ "css_read_more*" ];
            Bunyad_CZ.presetsNotice.setup(setting, affected, "_n_read_more_style");
        });
        api(optionsKey + "[header_layout]", setting => {
            const affected = [ "css_header_*", "css_topbar_*", "css_nav_*", "css_drop_*", "css_logo_*" ];
            Bunyad_CZ.presetsNotice.setup(setting, affected, "_n_header_layout");
        });
        api(optionsKey + "[sidebar_titles_style]", setting => {
            const affected = [ "css_sidebar_title_*" ];
            Bunyad_CZ.presetsNotice.setup(setting, affected, "_n_sidebar_titles");
        });
    })(jQuery, wp.customize, _);
} ]);