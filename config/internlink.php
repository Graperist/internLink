<?php

return [
    /*
    | Weekly Journal (Form 4)
    |
    | Weeks run Monday-Sunday. The journal for a week is due at the END of
    | the day below (ISO weekday: 1 = Monday ... 7 = Sunday). Default Friday.
    | Change with JOURNAL_DUE_WEEKDAY in .env - no code change needed.
    */
    'journal_due_weekday' => (int) env('JOURNAL_DUE_WEEKDAY', 5),
];
