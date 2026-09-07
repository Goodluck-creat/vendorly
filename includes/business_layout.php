<?php
// includes/business_layout.php — one shared shell for every vendor page.
// Desktop: fixed sidebar always visible. Mobile: off-canvas, toggled by a hamburger button.
// Call business_layout_head($activePage) right after <body>, and business_layout_foot() before </body>.

function business_layout_head(string $activePage, string $businessName): string {
    $nav = [
        'dashboard' => ['icon' => 'store',   'label' => 'Dashboard', 'href' => '/business/dashboard.php'],
        'orders'    => ['icon' => 'package', 'label' => 'Orders',    'href' => '/business/orders.php'],
        'products'  => ['icon' => 'image',   'label' => 'Products',  'href' => '/business/products.php'],
        'chat'      => ['icon' => 'chat',    'label' => 'Messages',  'href' => '/business/chat.php'],
    ];

    $navHtml = '';
    foreach ($nav as $key => $item) {
        $active = $key === $activePage ? 'active' : '';
        $navHtml .= '<a href="' . $item['href'] . '" class="sidenav-item ' . $active . '">' . icon($item['icon']) . '<span>' . $item['label'] . '</span></a>';
    }

    return '
    <div class="app-shell">
      <button class="mobile-toggle" onclick="document.querySelector(\'.sidebar\').classList.toggle(\'open\'); document.querySelector(\'.sidebar-backdrop\').classList.toggle(\'show\');" aria-label="Open menu">
        ' . icon('store') . '
      </button>
      <div class="sidebar-backdrop" onclick="document.querySelector(\'.sidebar\').classList.remove(\'open\'); this.classList.remove(\'show\');"></div>
      <aside class="sidebar">
        <div class="sidebar-brand">
          <div class="chip"></div>
          <div><strong>Vendorly</strong><span>' . htmlspecialchars($businessName) . '</span></div>
        </div>
        <nav class="sidenav">' . $navHtml . '</nav>
        <a href="/logout.php" class="sidenav-item logout">' . icon('log-out') . '<span>Log out</span></a>
      </aside>
      <main class="main-content">
    ';
}

function business_layout_foot(): string {
    return '</main></div>';
}

function business_layout_styles(): string {
    return '
    <style>
      .app-shell{ display:flex; min-height:100vh; }
      .sidebar{
        width:230px; flex:none; background:#fff; border-right:1px solid var(--line);
        display:flex; flex-direction:column; padding:22px 16px;
        position:sticky; top:0; height:100vh;
      }
      .sidebar-brand{ display:flex; align-items:center; gap:10px; padding:0 8px 20px; margin-bottom:8px; border-bottom:1px solid var(--line); }
      .sidebar-brand .chip{ width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg,var(--brand),var(--brand-deep)); flex:none; }
      .sidebar-brand strong{ display:block; font-size:14px; }
      .sidebar-brand span{ display:block; font-size:11px; color:var(--ink-soft); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }

      .sidenav{ display:flex; flex-direction:column; gap:2px; flex:1; }
      .sidenav-item{
        display:flex; align-items:center; gap:12px; padding:11px 10px; border-radius:9px;
        color:var(--ink-soft); font-size:13.5px; font-weight:600; text-decoration:none; transition:all .15s ease;
      }
      .sidenav-item .icon{ width:19px; height:19px; flex:none; }
      .sidenav-item:hover{ background:var(--paper); color:var(--ink); }
      .sidenav-item.active{ background:var(--brand-tint); color:var(--brand); }
      .sidenav-item.logout{ margin-top:auto; }

      .main-content{ flex:1; min-width:0; }

      .mobile-toggle{ display:none; }
      .sidebar-backdrop{ display:none; }

      @media (max-width:860px){
        .mobile-toggle{
          display:flex; align-items:center; justify-content:center; position:fixed; top:14px; left:14px; z-index:30;
          width:42px; height:42px; border-radius:11px; background:var(--brand); color:#fff; border:none; cursor:pointer;
          box-shadow:0 4px 14px rgba(15,61,62,.25);
        }
        .sidebar{
          position:fixed; top:0; left:0; height:100vh; z-index:40; transform:translateX(-100%);
          transition:transform .25s ease; box-shadow:0 0 30px rgba(0,0,0,.15);
        }
        .sidebar.open{ transform:translateX(0); }
        .sidebar-backdrop{
          display:block; position:fixed; inset:0; background:rgba(15,34,34,.45); z-index:35;
          opacity:0; pointer-events:none; transition:opacity .2s ease;
        }
        .sidebar-backdrop.show{ opacity:1; pointer-events:auto; }
        .main-content{ padding-top:56px; }
      }
    </style>
    ';
}
