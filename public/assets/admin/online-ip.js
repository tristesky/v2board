(function () {
  var ROUTE = "#/user/online-ip";
  var MENU_ANCHOR = "#/user";
  var FLAG = "__v2board_online_ip_page__";
  if (window[FLAG]) return;
  window[FLAG] = true;

  function securePath() {
    if (window.settings && window.settings.secure_path) {
      return String(window.settings.secure_path).replace(/^\/+|\/+$/g, "");
    }
    return location.pathname.replace(/^\/+|\/+$/g, "").split("/")[0] || "";
  }

  function apiUrl() {
    return "/api/v1/" + securePath() + "/onlineIp/fetch";
  }

  function token() {
    try {
      return window.localStorage.getItem("authorization") || "";
    } catch (e) {
      return "";
    }
  }

  function isRoute() {
    var hash = location.hash || "";
    var href = location.href || "";

    return hash === ROUTE
      || hash.indexOf(ROUTE + "?") === 0
      || href.indexOf("#/user/online-ip") !== -1;
  }

  function esc(v) {
    return String(v == null ? "" : v).replace(/[&<>"']/g, function (s) {
      return ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#039;"
      })[s];
    });
  }

  function ensureStyle() {
    if (document.getElementById("online-ip-style")) return;

    var el = document.createElement("style");
    el.id = "online-ip-style";
    el.innerHTML =
      ".online-ip-page{padding:24px;background:#f0f2f5;min-height:100vh}" +
      ".online-ip-card{background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)}" +
      ".online-ip-head{display:flex;justify-content:space-between;gap:16px;margin-bottom:16px}" +
      ".online-ip-title{margin:0 0 8px;font-size:22px;font-weight:600;color:rgba(0,0,0,.85)}" +
      ".online-ip-desc{color:rgba(0,0,0,.45);font-size:13px}" +
      ".online-ip-toolbar{display:grid;grid-template-columns:140px 220px 180px 140px 100px 100px;gap:10px;margin-bottom:14px}" +
      ".online-ip-toolbar input,.online-ip-toolbar select{height:32px;border:1px solid #d9d9d9;border-radius:4px;padding:0 10px;outline:none}" +
      ".online-ip-btn{height:32px;border:0;border-radius:4px;padding:0 14px;cursor:pointer;color:#fff;background:#1890ff}" +
      ".online-ip-btn.secondary{color:rgba(0,0,0,.65);background:#f5f5f5;border:1px solid #d9d9d9}" +
      ".online-ip-status{margin:10px 0 14px;font-size:13px;color:rgba(0,0,0,.45)}" +
      ".online-ip-status.error{color:#ff4d4f}" +
      ".online-ip-table-wrap{overflow-x:auto}" +
      ".online-ip-table{width:100%;border-collapse:collapse}" +
      ".online-ip-table th,.online-ip-table td{border-bottom:1px solid #f0f0f0;padding:10px 8px;font-size:13px;text-align:left;white-space:nowrap}" +
      ".online-ip-table th{background:#fafafa;color:rgba(0,0,0,.65);font-weight:600}" +
      ".online-ip-muted{color:rgba(0,0,0,.35)}" +
      ".online-ip-code{font-family:SFMono-Regular,Consolas,Menlo,monospace}" +
      ".online-ip-badge{display:inline-block;padding:2px 8px;border-radius:999px;color:#096dd9;background:#e6f7ff;border:1px solid #91d5ff}" +
      ".online-ip-pager{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:16px}";
    document.head.appendChild(el);
  }

  var state = { current: 1, total: 0, loading: false };

  function contentBox() {
    var main = document.getElementById("main-container");

    if (main) {
      return main;
    }

    var candidates = [
      "#page-container main",
      "#page-container .content",
      "main",
      ".content",
      ".ant-layout-content"
    ];

    for (var i = 0; i < candidates.length; i++) {
      var nodes = Array.prototype.slice.call(document.querySelectorAll(candidates[i]));

      for (var j = nodes.length - 1; j >= 0; j--) {
        var node = nodes[j];

        if (
          node &&
          !node.closest("#sidebar") &&
          !node.closest(".sidebar") &&
          !node.closest("nav")
        ) {
          return node;
        }
      }
    }

    return null;
  }

  function render() {
    if (!isRoute()) return;
    ensureStyle();

    var box = contentBox();
    if (!box) return;

    box.innerHTML =
      '<div class="online-ip-page">' +
        '<div class="online-ip-card">' +
          '<div class="online-ip-head">' +
            '<div>' +
              '<h1 class="online-ip-title">实时在线 IP</h1>' +
              '<div class="online-ip-desc">服务器 / 实时在线 IP。读取 Redis 在线缓存，显示用户当前连接 IP 与归属地；不写入历史数据库。</div>' +
            '</div>' +
            '<button class="online-ip-btn" id="onlineIpReload">刷新</button>' +
          '</div>' +
          '<div class="online-ip-toolbar">' +
            '<input id="onlineIpUserId" placeholder="用户ID">' +
            '<input id="onlineIpEmail" placeholder="邮箱关键词">' +
            '<input id="onlineIpIp" placeholder="IP关键词">' +
            '<select id="onlineIpPageSize">' +
              '<option value="20">每页 20 条</option>' +
              '<option value="50">每页 50 条</option>' +
              '<option value="100">每页 100 条</option>' +
              '<option value="200">每页 200 条</option>' +
            '</select>' +
            '<button class="online-ip-btn" id="onlineIpSearch">查询</button>' +
            '<button class="online-ip-btn secondary" id="onlineIpReset">重置</button>' +
          '</div>' +
          '<label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;color:rgba(0,0,0,.45);font-size:13px;">' +
            '<input id="onlineIpAuto" type="checkbox" checked> 每 10 秒自动刷新' +
          '</label>' +
          '<div id="onlineIpStatus" class="online-ip-status">准备加载数据...</div>' +
          '<div class="online-ip-table-wrap">' +
            '<table class="online-ip-table">' +
              '<thead><tr>' +
                '<th>用户ID</th><th>邮箱</th><th>节点</th><th>节点类型</th><th>IP</th><th>归属地</th><th>上报时间</th><th>距今</th><th>标记</th>' +
              '</tr></thead>' +
              '<tbody id="onlineIpTbody"><tr><td colspan="9" class="online-ip-muted">正在加载...</td></tr></tbody>' +
            '</table>' +
          '</div>' +
          '<div class="online-ip-pager">' +
            '<button class="online-ip-btn secondary" id="onlineIpPrev">上一页</button>' +
            '<span id="onlineIpPageInfo" class="online-ip-muted">第 1 页</span>' +
            '<button class="online-ip-btn secondary" id="onlineIpNext">下一页</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    bind();
    load();
  }

  function params() {
    var p = new URLSearchParams();
    p.set("current", state.current);
    p.set("pageSize", document.getElementById("onlineIpPageSize").value || "20");

    var userId = document.getElementById("onlineIpUserId").value.trim();
    var email = document.getElementById("onlineIpEmail").value.trim();
    var ip = document.getElementById("onlineIpIp").value.trim();

    if (userId) p.set("user_id", userId);
    if (email) p.set("email", email);
    if (ip) p.set("ip", ip);

    return p;
  }

  async function load() {
    if (!isRoute() || state.loading) return;
    state.loading = true;

    var status = document.getElementById("onlineIpStatus");
    var tbody = document.getElementById("onlineIpTbody");
    var auth = token();

    if (!auth) {
      status.className = "online-ip-status error";
      status.textContent = "没有读取到 localStorage.authorization。请在当前域名重新登录后台。";
      state.loading = false;
      return;
    }

    status.className = "online-ip-status";
    status.textContent = "正在加载...";

    try {
      var res = await fetch(apiUrl() + "?" + params().toString(), {
        method: "GET",
        credentials: "same-origin",
        headers: {
          "Accept": "application/json",
          "authorization": auth
        }
      });

      if (!res.ok) throw new Error("HTTP " + res.status);

      var json = await res.json();
      var rows = Array.isArray(json.data) ? json.data : [];
      state.total = Number(json.total || rows.length || 0);

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="online-ip-muted">暂无在线 IP 数据。若用户刚连接，请等待节点上报。</td></tr>';
      } else {
        tbody.innerHTML = rows.map(function (r) {
          var nodeName = r.node_name ? esc(r.node_name) : '<span class="online-ip-muted">未知</span>';
          var nodeType = esc(r.node_type || "") + (r.node_id ? "#" + esc(r.node_id) : "");
          var age = r.age_seconds == null ? '<span class="online-ip-muted">未知</span>' : esc(r.age_seconds) + " 秒";

          return '<tr>' +
            '<td>' + esc(r.user_id) + '</td>' +
            '<td>' + esc(r.email) + '</td>' +
            '<td>' + nodeName + '</td>' +
            '<td><span class="online-ip-badge">' + nodeType + '</span></td>' +
            '<td class="online-ip-code">' + esc(r.ip) + '</td>' +
            '<td>' + esc(r.location) + '</td>' +
            '<td>' + (r.last_update_time ? esc(r.last_update_time) : '<span class="online-ip-muted">未知</span>') + '</td>' +
            '<td>' + age + '</td>' +
            '<td>' + (r.ip_tag ? esc(r.ip_tag) : '<span class="online-ip-muted">-</span>') + '</td>' +
          '</tr>';
        }).join("");
      }

      var size = Number(document.getElementById("onlineIpPageSize").value || 20);
      var pages = Math.max(1, Math.ceil(state.total / size));
      document.getElementById("onlineIpPageInfo").textContent =
        "第 " + state.current + " / " + pages + " 页，共 " + state.total + " 条";

      status.textContent = "加载完成：" + new Date().toLocaleString();
    } catch (e) {
      status.className = "online-ip-status error";
      status.textContent = "加载失败：" + e.message + "；接口：" + apiUrl();
    } finally {
      state.loading = false;
    }
  }

  function bind() {
    document.getElementById("onlineIpReload").onclick = load;

    document.getElementById("onlineIpSearch").onclick = function () {
      state.current = 1;
      load();
    };

    document.getElementById("onlineIpReset").onclick = function () {
      document.getElementById("onlineIpUserId").value = "";
      document.getElementById("onlineIpEmail").value = "";
      document.getElementById("onlineIpIp").value = "";
      state.current = 1;
      load();
    };

    document.getElementById("onlineIpPrev").onclick = function () {
      if (state.current > 1) {
        state.current--;
        load();
      }
    };

    document.getElementById("onlineIpNext").onclick = function () {
      var size = Number(document.getElementById("onlineIpPageSize").value || 20);
      var pages = Math.max(1, Math.ceil(state.total / size));
      if (state.current < pages) {
        state.current++;
        load();
      }
    };

    document.getElementById("onlineIpPageSize").onchange = function () {
      state.current = 1;
      load();
    };
  }

  function removeOnlineIpMenusOutside(parent) {
    var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-online-ip-menu="1"]'));

    nodes.forEach(function (node) {
      if (node.parentElement !== parent && node.parentElement) {
        node.parentElement.removeChild(node);
      }
    });
  }

  function clearSelectedClass(el) {
    if (!el || !el.querySelectorAll) return;

    var nodes = [el].concat(Array.prototype.slice.call(el.querySelectorAll("*")));

    nodes.forEach(function (node) {
      if (!node.className || typeof node.className !== "string") return;

      node.className = node.className
        .replace(/\bant-menu-item-selected\b/g, "")
        .replace(/\bant-menu-submenu-selected\b/g, "")
        .replace(/\bant-menu-item-active\b/g, "")
        .replace(/\bant-menu-submenu-active\b/g, "");
    });
  }

  function findUserManageItem() {
    var items = Array.prototype.slice.call(document.querySelectorAll("li.ant-menu-item"));

    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var link = item.querySelector('a[href="#/user"], a[href*="#/user"]');
      var title = item.querySelector(".ant-menu-title-content");

      var titleText = title ? (title.textContent || "").trim() : "";
      var itemText = (item.textContent || "").trim();

      if (link || titleText === "用户管理" || itemText === "用户管理") {
        return item;
      }
    }

    return null;
  }

  function rewriteOnlineIpMenuItem(item) {
    item.setAttribute("data-online-ip-menu", "1");
    clearSelectedClass(item);

    var link = item.querySelector("a");

    if (link) {
      link.setAttribute("href", ROUTE);
      link.onclick = function (e) {
        e.preventDefault();
        location.hash = ROUTE;
      };
    } else {
      item.onclick = function (e) {
        e.preventDefault();
        location.hash = ROUTE;
      };
    }

    var title = item.querySelector(".ant-menu-title-content");

    if (title) {
      title.textContent = "实时在线 IP";
      return;
    }

    var candidates = Array.prototype.slice.call(item.querySelectorAll("span,a,div"));

    for (var i = candidates.length - 1; i >= 0; i--) {
      var text = (candidates[i].textContent || "").trim();

      if (text && text.length <= 20) {
        candidates[i].textContent = "实时在线 IP";
        return;
      }
    }
  }

  function addMenu() {
    // 菜单已经在 umi.js 的 nav 配置中注册，这里不再做 DOM 注入。
  }

  function handle() {
    addMenu();

    if (!isRoute()) return;

    setTimeout(render, 100);
    setTimeout(render, 350);
    setTimeout(render, 800);
  }

  var lastHref = "";

  function routeTick() {
    if (lastHref !== location.href) {
      lastHref = location.href;
      handle();
    }

    if (isRoute() && !document.getElementById("onlineIpTbody")) {
      render();
    }
  }

  function forceOpenOnlineIp() {
    if (location.hash !== ROUTE) {
      location.hash = ROUTE;
    }

    setTimeout(handle, 80);
    setTimeout(handle, 250);
    setTimeout(handle, 600);
    setTimeout(handle, 1200);
  }

  function bindMenuClickFallback() {
    if (window.__online_ip_menu_click_fallback__) return;
    window.__online_ip_menu_click_fallback__ = true;

    document.addEventListener("click", function (e) {
      var target = e.target;

      if (!target || !target.closest) return;

      var node = target.closest(".nav-main-link, .nav-main-item, a, li");

      if (!node) return;

      var text = (node.textContent || "").replace(/\s+/g, "");

      if (text.indexOf("实时在线IP") === -1) return;

      e.preventDefault();
      e.stopPropagation();

      forceOpenOnlineIp();
    }, true);
  }

  function openOnlineIpPage() {
    if (location.hash !== ROUTE) {
      location.hash = ROUTE;
    }

    setTimeout(render, 80);
    setTimeout(render, 200);
    setTimeout(render, 500);
    setTimeout(render, 1000);
    setTimeout(render, 1600);
  }

  function bindOnlineIpMenuClick() {
    if (window.__online_ip_menu_click_bound__) return;
    window.__online_ip_menu_click_bound__ = true;

    document.addEventListener("click", function (e) {
      var target = e.target;

      if (!target || !target.closest) return;

      var node = target.closest(".nav-main-link, .nav-main-item, a, li");
      if (!node) return;

      var text = (node.textContent || "").replace(/\s+/g, "");

      if (text.indexOf("实时在线IP") === -1) return;

      e.preventDefault();
      e.stopPropagation();

      if (e.stopImmediatePropagation) {
        e.stopImmediatePropagation();
      }

      openOnlineIpPage();
    }, true);
  }

  window.addEventListener("hashchange", handle);
  window.addEventListener("popstate", handle);

  var observer = new MutationObserver(function () {
    if (isRoute() && !document.getElementById("onlineIpTbody")) {
      render();
    }
  });

  function start() {
    observer.observe(document.getElementById("root") || document.body, {
      childList: true,
      subtree: true
    });

    handle();
    setInterval(routeTick, 300);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }

  setInterval(function () {
    if (!isRoute()) return;
    var auto = document.getElementById("onlineIpAuto");
    if (auto && auto.checked) load();
  }, 10000);
})();
