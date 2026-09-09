<style>
    /*
        GLOBAL RESETS
    */

    img {
        border: none;
        -ms-interpolation-mode: bicubic;
        max-width: 100%;
    }

    body {
        background-color: #d2d6dc;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        -webkit-font-smoothing: antialiased;
        font-size: 14px;
        line-height: 1.5;
        margin: 0;
        padding: 0;
        -ms-text-size-adjust: 100%;
        -webkit-text-size-adjust: 100%;
    }

    table {
        border-collapse: collapse;
        mso-table-lspace: 0pt;
        mso-table-rspace: 0pt;
        width: 100%;
    }

    table thead {
        display: table-header-group;
        vertical-align: middle;
        border-color: inherit;
    }

    table tbody {
        box-sizing: border-box;
        border-width: 0;
        border-style: solid;
    }

    table td {
        font-family: sans-serif;
        font-size: 14px;
        vertical-align: top;
    }

    table th {
        display: table-cell;
        vertical-align: inherit;
        font-weight: bold;
        text-align: left;
    }

    .table_wrapper {
        border-width: 1px;
        border-radius: 0.25rem;
        border-color: #e5e7eb;
        border-style: solid;
    }

    .data_table th {
        font-size: 12px;
        color: #6b7280;
        padding: 0.75rem 1rem 0.75rem 0.5rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .data_table td {
        box-sizing: border-box;
        border-width: 0;
        border-style: solid;
        border-color: #e5e7eb;
        padding: 0.75rem 0.5rem;
        border-top-width: 1px;
        font-size: 12px;
    }

    /*
        BODY & CONTAINER
    */

    .body {
        background-color: #f7fafc;
        width: 100%;
    }

    /*
        Set a max-width, and make it display as block so it will automatically stretch to that width,
        but will also shrink down on a phone or something
    */
    .container {
        display: block;
        Margin: 0 auto !important;
        /* makes it centered */
        max-width: 580px;
        padding: 10px;
        width: 580px;
    }

    /* This should also be a block element, so that it will fill 100% of the .container */
    .content {
        box-sizing: border-box;
        display: block;
        Margin: 0 auto;
        max-width: 580px;
        padding: 10px;
    }

    /*
        HEADER, FOOTER, MAIN
    */

    .main {
        background: #ffffff;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        /*box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);*/
        width: 100%;
    }

    .wrapper {
        box-sizing: border-box;
        padding: 50px;
        padding-top: 0;
    }

    .logo {
        max-width: 275px;
        max-height: 100px;
        height: auto;
        width: auto;
    }

    .content-block {
        padding-bottom: 10px;
        padding-top: 10px;
    }

    .footer {
        clear: both;
        Margin-top: 10px;
        text-align: center;
        width: 100%;
    }

    .footer td,
    .footer p,
    .footer span,
    .footer a {
        color: #999999;
        font-size: 12px;
        text-align: center;
    }

    /*
        TYPOGRAPHY
    */

    h1,
    h2,
    h3,
    h4 {
        color: #000000;
        font-family: sans-serif;
        font-weight: 400;
        line-height: 1.4;
        margin: 0;
        margin-bottom: 15px;
    }

    h1 {
        font-size: 35px;
        font-weight: 300;
        text-align: center;
        text-transform: capitalize;
    }

    p,
    ul,
    ol {
        font-family: sans-serif;
        font-size: 14px;
        font-weight: normal;
        margin: 0;
        Margin-bottom: 15px;
    }

    p li,
    ul li,
    ol li {
        list-style-position: inside;
        margin-left: 5px;
    }

    a {
        color: #3498db;
        text-decoration: underline;
        word-break: break-word;
    }

    /*
        BUTTONS
    */

    .btn {
        box-sizing: border-box;
        width: 100%;
    }

    .btn a {
        background-color: #ffffff;
        border-color: #67e8f9;
        border-radius: 0.375rem;
        border-width: 1px;
        box-sizing: border-box;
        color: #0e7490;
        cursor: pointer;
        display: inline-block;
        padding: 0.5rem 1rem;
        font-size: 1rem;
        line-height: 1.5rem;
        font-weight: 500;
        text-decoration: none;
        transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }
    .btn a:hover {
        color: #06b6d4 !important;
    }
    .btn-primary a {
        background-color: #0891b2;
        border-color: transparent;
        color: #ffffff;
    }
    .btn-primary a:hover {
        color: #ffffff !important;
        background-color: #06b6d4 !important;
    }

    .btn > tbody > tr > td {
        padding-bottom: 15px;
    }

    .btn table {
        width: auto;
    }

    .btn table td {
        background-color: #ffffff;
        border-radius: 5px;
        text-align: center;
    }

    /*
        CUSTOM + UTILITIES
    */

    .masthead {
        padding: 40px;
        padding-bottom: 35px;
    }

    .last {
        margin-bottom: 0;
    }

    .first {
        margin-top: 0;
    }

    .align-center {
        text-align: center;
    }

    .align-right {
        text-align: right;
    }

    .align-left {
        text-align: left;
    }

    .clear {
        clear: both;
    }

    .mt0 {
        margin-top: 0;
    }

    .mb0 {
        margin-bottom: 0;
    }

    .mb {
        margin-bottom: 15px;
    }

    .border-top {
        border-top: 1px solid #f9f9f9;
        padding-top: 25px;
    }

    .preheader {
        color: transparent;
        display: none;
        height: 0;
        max-height: 0;
        max-width: 0;
        opacity: 0;
        overflow: hidden;
        mso-hide: all;
        visibility: hidden;
        width: 0;
    }

    .powered-by a {
        text-decoration: none;
    }

    hr {
        border: 0;
        border-bottom: 1px solid #f6f6f6;
        Margin: 20px 0;
    }

    /*
        RESPONSIVE AND MOBILE FRIENDLY STYLES
    */

    @media only screen and (max-width: 620px) {
        table[class=body] h1 {
            font-size: 28px !important;
            margin-bottom: 10px !important;
        }

        table[class=body] p,
        table[class=body] ul,
        table[class=body] ol,
        table[class=body] td,
        table[class=body] span,
        table[class=body] a {
            font-size: 16px !important;
        }

        table[class=body] .wrapper,
        table[class=body] .article {
            padding: 10px !important;
        }

        table[class=body] .content {
            padding: 0 !important;
        }

        table[class=body] .container {
            padding: 0 !important;
            width: 100% !important;
        }

        table[class=body] .main {
            border-left-width: 0 !important;
            border-radius: 0 !important;
            border-right-width: 0 !important;
        }

        table[class=body] .btn table {
            width: 100% !important;
        }

        table[class=body] .btn a {
            width: 100% !important;
        }

        table[class=body] .img-responsive {
            height: auto !important;
            max-width: 100% !important;
            width: auto !important;
        }
    }

    /*
        PRESERVE THESE STYLES IN THE HEAD
    */

    @media all {
        .ExternalClass {
            width: 100%;
        }

        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
            line-height: 100%;
        }

        .apple-link a {
            color: inherit !important;
            font-family: inherit !important;
            font-size: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
            text-decoration: none !important;
        }
    }
</style>
