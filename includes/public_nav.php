<?php
// includes/public_nav.php — the top bar used on every public (guest-accessible) marketplace page.
// Shows Login/Sign up for guests, or account links for whoever's logged in (any role).

function render_public_nav(string $searchValue = ''): string {
    $loggedIn = is_logged_in();
    $accountLinks = '';

    if ($loggedIn) {
        $role = current_role();
        $accountHome = role_home_path($role);
        if (is_guest()) {
            $name = htmlspecialchars($_SESSION['name']);
            $accountLinks = '
                <span style="color:#5C6E6C;">Hi, ' . $name . '</span>
                <a href="/customer/orders.php">My orders</a>
                <a href="/customer/claim_account.php" class="btn primary small">Save my account</a>
                <a href="/logout.php">Log out</a>
            ';
        } else {
            $accountLinks = '
                <a href="/customer/orders.php">My orders</a>
                <a href="' . htmlspecialchars($accountHome) . '">My account</a>
                <a href="/logout.php">Log out</a>
            ';
        }
    } else {
        $accountLinks = '
            <a href="/login.php">Log in</a>
            <a href="/register.php" class="btn primary small">Sign up</a>
        ';
    }

    return '
    <header class="site">
      <div class="wrap headerbar">
        <a href="/customer/home.php" class="wordmark"><div class="chip"></div>Vendorly</a>
        <form class="searchbar" method="GET" action="/customer/home.php">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input type="text" name="q" placeholder="Search products or businesses..." value="' . htmlspecialchars($searchValue) . '">
        </form>
        <div class="headeractions">' . $accountLinks . '</div>
      </div>
    </header>
    <style>
      header.site{ border-bottom:1px solid #DAE3E1; margin-bottom:10px; }
      .headerbar{ display:flex; align-items:center; gap:20px; padding:16px 0; max-width:820px; margin:0 auto; }
      .wordmark{ display:flex; align-items:center; gap:9px; font-family:"Inter",sans-serif; font-weight:700; font-size:17px; text-decoration:none; color:#132323; flex:none; }
      .wordmark .chip{ width:24px; height:24px; border-radius:7px; background:linear-gradient(135deg,#0F3D3E,#082627); }
      .searchbar{ flex:1; display:flex; align-items:center; gap:8px; background:#F5F7F5; border:1px solid #DAE3E1; border-radius:9px; padding:9px 12px; }
      .searchbar input{ border:none; background:none; outline:none; font-size:13.5px; width:100%; font-family:inherit; }
      .searchbar svg{ opacity:.55; flex:none; }
      .headeractions{ display:flex; align-items:center; gap:16px; font-size:13.5px; font-weight:600; flex:none; }
      .headeractions a{ text-decoration:none; color:#132323; }
      .headeractions .btn.primary{ background:#0F3D3E; color:#fff; padding:9px 16px; border-radius:8px; }
      @media (max-width:560px){ .headerbar{ flex-wrap:wrap; } .searchbar{ order:3; width:100%; } }
    </style>
    ';
}
