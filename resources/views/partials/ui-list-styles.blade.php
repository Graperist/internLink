{{-- Shared look for the portal's list pages (Evaluations, Partner Agencies): summary tiles, section icons, status pills, icon actions. --}}
<style>
    .ev-head { display: flex; align-items: center; gap: .75rem; flex: 1 1 0; min-width: 260px; }
    .ev-icon { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 1.2rem; flex: none; }
    .ev-icon.blue { background: #e8eef7; color: #1c3f66; }
    .ev-icon.gold { background: #fff3d6; color: #8a5d00; }
    .ev-stat { border: 1px solid #e6eaf0; border-radius: 12px; padding: .8rem 1rem; background: #fff; height: 100%; }
    .ev-stat .n { font-size: 1.35rem; font-weight: 800; color: #14213a; line-height: 1.1; }
    .ev-stat .n small { font-size: .85rem; font-weight: 600; color: #8a94a6; }
    .ev-stat .l { font-size: .78rem; color: #5d6b80; }
    .ev-stat .bar { height: 4px; border-radius: 99px; background: #eef1f6; margin-top: .5rem; overflow: hidden; }
    .ev-stat .bar > span { display: block; height: 100%; background: #1f9d3a; }
    .ev-stat.alert-tile { border-color: #f1b0b7; background: #fff7f8; }
    .ev-stat.alert-tile .n { color: #b42318; }
    .ev-stat .ic { float: right; width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: #e8eef7; color: #1c3f66; font-size: 1.05rem; }
    .ev-stat.alert-tile .ic { background: #fde2e4; color: #b42318; }
    .ui-table { --bs-table-bg: transparent; }
    .ui-table thead th { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #6c7a90; white-space: nowrap; font-weight: 700; border-bottom-color: #e6eaf0; background: #f8f9fb; }
    .ui-table thead tr.ev-group th { background: #fff; color: #1c3f66; font-size: .68rem; padding-bottom: .25rem; }
    .ui-table thead .ev-sub { display: block; text-transform: none; letter-spacing: 0; font-weight: 500; color: #8a94a6; font-size: .72rem; }
    .ui-table tbody td { border-bottom-color: #eef1f5; padding-top: .8rem; padding-bottom: .8rem; }
    .ui-table tbody tr:hover > td { background: #fafbfd; }
    .ev-pill { display: inline-flex; align-items: center; gap: .3rem; border-radius: 999px; padding: .2rem .6rem; font-size: .72rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap; }
    .ev-pill.ok { background: #e7f6ec; color: #1d6b3a; }
    .ev-pill.wait { background: #f1f3f7; color: #6b7689; font-weight: 600; }
    .ev-pill.warn { background: #fff3d6; color: #8a5d00; }
    .ev-pill.bad { background: #fde2e4; color: #b42318; }
    .ev-pill.blue { background: #e8eef7; color: #1c3f66; }
    .ev-pill.ro { background: #eef1f6; color: #5d6b80; font-weight: 600; }
    .ev-acts { display: inline-flex; gap: .25rem; margin-left: .35rem; vertical-align: middle; }
    .ev-act { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #dfe5ee; display: inline-grid; place-items: center; color: #1c3f66; background: #fff; text-decoration: none; font-size: .95rem; }
    .ev-act.danger { color: #b42318; }
    .ev-act.danger:hover { background: #fff1f2; border-color: #f1b0b7; color: #912018; }
    .ev-act.ev-locked { background: #fff3d6; border-color: #f3d38a; color: #8a5d00; }
    button.ev-act { padding: 0; }
    .ev-act:hover { background: #eef3fb; border-color: #c9d6ea; color: #0b2f86; }
    .ev-chip { border: 1px solid #dfe5ee; background: #fff; border-radius: 999px; padding: .2rem .65rem; font-size: .8rem; color: #14213a; display: inline-flex; align-items: center; gap: .35rem; }
    .ev-chip:hover { background: #eef3fb; border-color: #c9d6ea; }
    .ev-avatar { width: 34px; height: 34px; border-radius: 50%; background: #e8eef7; color: #1c3f66; font-weight: 700; font-size: .78rem; display: grid; place-items: center; flex: none; }
    .ev-count { font-size: .72rem; color: #8a94a6; font-weight: 600; }
    .ev-agency { font-weight: 700; color: #14213a; text-decoration: none; }
    a.ev-agency:hover { color: #0b2f86; text-decoration: underline; }
</style>
