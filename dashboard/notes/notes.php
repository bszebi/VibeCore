<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/layout/header.php';
?>

<link rel="stylesheet" href="../../assets/css/style.css">
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    .notes-container {
        min-height: calc(100vh - 180px);
        padding: 0;
        position: relative;
        background-color: #f8f9fa;
    }

    .new-note-btn {
        position: fixed;
        bottom: 20px;
        right: 30px;
        background: #0d6efd;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 100;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .new-note-btn .btn-text {
        display: inline;
    }

    .new-note-btn:hover {
        background: #0b5ed7;
        transform: translateY(-2px);
        box-shadow: 0 6px 8px rgba(0,0,0,0.15);
    }

    .content-wrapper {
        display: grid;
        grid-template-columns: 400px minmax(0, 1000px);
        gap: 30px;
        padding: 20px;
        max-width: 1600px;
        margin: 0 auto;
        min-height: calc(100vh - 220px);
        position: relative;
        overflow: hidden;
    }

    .note-list {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 20px;
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 15px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .note-list::-webkit-scrollbar {
        width: 6px;
    }

    .note-list::-webkit-scrollbar-track {
        background: #f8f9fa;
    }

    .note-list::-webkit-scrollbar-thumb {
        background-color: #1e2a38;
        border-radius: 3px;
    }

    .note-list::-webkit-scrollbar-thumb:hover {
        background-color: #2c3e50;
    }

    .note-item {
        background: transparent;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        cursor: pointer;
        transition: all 0.3s ease;
        overflow: visible;
        margin: 0;
    }

    .note-item:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-color: #0d6efd;
    }

    .note-item.active {
        border-color: #0d6efd;
        background: transparent;
        box-shadow: 0 4px 8px rgba(13, 110, 253, 0.15);
    }

    .note-item.active .note-title {
        color: #0d6efd;
        font-weight: 600;
    }

    .note-title {
        font-size: 18px;
        color: #2c3e50;
        margin: 0;
        font-weight: 500;
        flex: 1;
        padding: 5px 10px;
        transition: all 0.2s ease;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .note-title:hover {
        color: #0d6efd;
    }

    .editor-container {
        background: #fff;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        height: calc(100vh - 260px);
        display: none;
        flex-direction: column;
        max-width: 1000px;
        width: 100%;
        position: relative;
        z-index: 1000;
        overflow: hidden;
    }

    .editor-content {
        display: flex;
        flex-direction: column;
        height: calc(100% - 80px); /* Hely a gombnak */
    }

    #titleInput {
        font-size: 24px;
        padding: 10px 15px;
        margin: 20px 0;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        width: 100%;
        background: transparent;
    }

    #editor {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0; /* Fontos a görgetéshez */
    }

    .ql-container {
        flex: 1;
        overflow: hidden;
        font-size: 16px;
        border: 1px solid #e3e6f0 !important;
        border-radius: 0 0 8px 8px;
        display: flex;
        flex-direction: column;
    }

    .ql-toolbar {
        border: 1px solid #e3e6f0 !important;
        border-radius: 8px 8px 0 0;
        background: #f8f9fa;
        padding: 8px;
    }

    .ql-editor {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: transparent;
    }

    .button-container {
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        padding: 20px 0 0 0;
    }

    .save-btn {
        background: #0d6efd;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1em;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-left: 30px;
        position: relative;
        overflow: hidden;
    }

    .save-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
        opacity: 0.8;
    }

    .save-btn.saving {
        background: #6c757d;
        pointer-events: none;
    }

    .save-btn.saving::after {
        content: '';
        position: absolute;
        left: -100%;
        top: 0;
        height: 100%;
        width: 200%;
        background: linear-gradient(
            90deg,
            transparent 0%,
            rgba(255,255,255,0.2) 50%,
            transparent 100%
        );
        animation: saving-animation 1.5s infinite linear;
    }

    @keyframes saving-animation {
        0% { left: -100%; }
        100% { left: 100%; }
    }

    .save-btn.success {
        background: #198754;
    }

    .save-btn i.success-icon {
        display: none;
    }

    .save-btn.success i.success-icon {
        display: inline-block;
    }

    .save-btn.success i.save-icon {
        display: none;
    }

    .save-btn i {
        font-size: 1.1em;
    }

    .close-btn {
        position: absolute;
        right: 0;
        top: 0;
        background: transparent;
        border: none;
        font-size: 24px;
        color: #6c757d;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-top-right-radius: 15px;
        transition: all 0.2s ease;
        z-index: 1001;
    }

    .close-btn:hover {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .ql-toolbar.ql-snow {
        border: 1px solid #e3e6f0;
        border-radius: 8px 8px 0 0;
        background: transparent;
        padding: 12px;
    }

    .ql-container.ql-snow {
        border: 1px solid #e3e6f0;
        border-top: none;
        border-radius: 0 0 8px 8px;
        font-size: 16px;
    }

    .ql-editor {
        min-height: 200px;
        font-family: Arial, sans-serif;
        text-align: inherit !important;
    }

    /* Alap tooltip stílus minden gombhoz */
    .ql-toolbar button,
    .ql-toolbar .ql-picker-label {
        position: relative !important;
    }

    /* Tooltip megjelenítése csak a formázási gomboknál */
    .ql-toolbar button:not(.ql-header):not(.ql-align):not(.ql-background):not(.ql-color)::before {
        content: attr(data-tooltip) !important;
        position: absolute !important;
        top: -25px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        padding: 3px 8px !important;
        background: #2c3e50 !important;
        color: white !important;
        font-size: 12px !important;
        border-radius: 3px !important;
        white-space: nowrap !important;
        visibility: hidden !important;
        opacity: 0 !important;
        transition: all 0.2s ease !important;
        pointer-events: none !important;
        z-index: 1000 !important;
    }

    .ql-toolbar button:not(.ql-header):not(.ql-align):not(.ql-background):not(.ql-color):hover::before {
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* Címsor picker stílusok */
    .ql-snow .ql-picker.ql-header {
        width: auto !important;
        min-width: 98px !important;
    }

    .ql-snow .ql-picker.ql-header .ql-picker-label {
        padding: 0 8px !important;
        display: flex !important;
        align-items: center !important;
    }

    /* Címsor szövegek */
    .ql-snow .ql-picker.ql-header .ql-picker-label::before,
    .ql-snow .ql-picker.ql-header .ql-picker-item::before {
        position: static !important;
        display: inline !important;
        visibility: visible !important;
        opacity: 1 !important;
        font-size: 14px !important;
        color: inherit !important;
        background: none !important;
        transform: none !important;
        padding: 0 !important;
    }

    /* Igazítás gombok alapstílusa */
    .ql-align.ql-picker {
        width: 32px !important;
    }

    .ql-align .ql-picker-label {
        padding: 0 4px !important;
    }

    /* Igazítás ikonok */
    .ql-picker.ql-align .ql-picker-label::before,
    .ql-picker.ql-align .ql-picker-item::before {
        content: '' !important;
        display: inline-block !important;
        width: 18px !important;
        height: 18px !important;
        background-size: contain !important;
        background-repeat: no-repeat !important;
        background-position: center !important;
        vertical-align: middle !important;
        position: static !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* Igazítás specifikus ikonok */
    .ql-picker.ql-align .ql-picker-label[data-value=""]::before,
    .ql-picker.ql-align .ql-picker-item[data-value=""]::before {
        background-image: url('../../assets/img/align-left.png') !important;
    }

    .ql-picker.ql-align .ql-picker-label[data-value="center"]::before,
    .ql-picker.ql-align .ql-picker-item[data-value="center"]::before {
        background-image: url('../../assets/img/format.png') !important;
    }

    .ql-picker.ql-align .ql-picker-label[data-value="right"]::before,
    .ql-picker.ql-align .ql-picker-item[data-value="right"]::before {
        background-image: url('../../assets/img/align-right.png') !important;
    }

    .ql-picker.ql-align .ql-picker-label[data-value="justify"]::before,
    .ql-picker.ql-align .ql-picker-item[data-value="justify"]::before {
        background-image: url('../../assets/img/justify.png') !important;
    }

    /* Legördülő menü stílusa */
    .ql-picker-options {
        padding: 5px !important;
        background: white !important;
        border: 1px solid #ccc !important;
        border-radius: 4px !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    }

    .ql-picker.ql-align .ql-picker-options {
        width: auto !important;
        min-width: 120px !important;
    }

    .ql-picker-item {
        display: flex !important;
        align-items: center !important;
        padding: 5px 8px !important;
        cursor: pointer !important;
        border-radius: 3px !important;
        transition: background-color 0.2s !important;
        gap: 8px !important;
    }

    .ql-picker-item:hover {
        background-color: #f0f0f0 !important;
    }

    /* Igazítás legördülő menü szövegei */
    .ql-picker.ql-align .ql-picker-item {
        padding-left: 30px !important;
        position: relative !important;
    }

    .ql-picker.ql-align .ql-picker-item::before {
        position: absolute !important;
        left: 8px !important;
    }

    .ql-picker.ql-align .ql-picker-item::after {
        content: attr(data-value) !important;
        font-size: 14px !important;
        color: inherit !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

    .ql-picker.ql-align .ql-picker-item[data-value=""]::after { content: 'Balra' !important; }
    .ql-picker.ql-align .ql-picker-item[data-value="center"]::after { content: 'Középre' !important; }
    .ql-picker.ql-align .ql-picker-item[data-value="right"]::after { content: 'Jobbra' !important; }
    .ql-picker.ql-align .ql-picker-item[data-value="justify"]::after { content: 'Sorkizárt' !important; }

    /* Aktív állapot */
    .ql-picker-label.ql-active,
    .ql-picker-item.ql-selected {
        color: #06c !important;
    }

    /* Quill alapértelmezett ikonok elrejtése */
    .ql-align .ql-stroke {
        display: none !important;
    }

    /* Reszponzív dizájn */
    @media (max-width: 1400px) {
        .content-wrapper {
            grid-template-columns: 350px minmax(0, 800px);
            max-width: 1200px;
            gap: 20px;
        }
    }

    @media (max-width: 1200px) {
        .content-wrapper {
            grid-template-columns: 300px minmax(0, 700px);
            padding: 15px;
            gap: 15px;
        }
    }

    @media (max-width: 992px) {
        .content-wrapper {
            grid-template-columns: 1fr;
            padding: 15px;
        }

        .note-list {
            max-height: none;
            height: auto;
            min-height: 200px;
            margin-bottom: 20px;
        }

        .editor-container {
            height: auto;
            min-height: 500px;
            margin-top: 20px;
            padding: 20px;
        }

        .ql-toolbar.ql-snow {
            padding: 10px !important;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
        }

        .ql-toolbar.ql-snow .ql-formats {
            margin-right: 0 !important;
            margin-bottom: 5px;
        }

        .ql-toolbar button {
            width: 32px !important;
            height: 32px !important;
            padding: 6px !important;
        }
    }

    @media (max-width: 768px) {
        .content-wrapper {
            padding: 10px;
        }

        .note-list {
            padding: 15px;
        }

        .note-item {
            padding: 12px 15px;
        }

        .note-title {
            font-size: 16px;
        }

        .editor-container {
            padding: 15px;
        }

        #titleInput {
            font-size: 18px;
            padding: 8px 12px;
        }

        .button-container {
            padding: 15px 0 0 0;
        }

        .save-btn {
            width: 100%;
            justify-content: center;
            margin-left: 0;
        }

        .new-note-btn {
            padding: 12px;
            right: 20px;
            bottom: 20px;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            justify-content: center;
        }

        .new-note-btn .btn-text {
            display: none;
        }

        .new-note-btn i {
            font-size: 24px;
            margin: 0;
        }

        .arrow-pointer {
            bottom: 85px;
            right: 35px;
            font-size: 2.5em;
        }

        .empty-state-message {
            font-size: 16px;
            padding: 0 20px;
            margin-bottom: 60px;
        }

        .dropdown-content {
            right: auto;
            left: 0;
        }

        .note-item:last-child .dropdown-content {
            bottom: 100%;
            top: auto;
        }
    }

    @media (max-width: 480px) {
        .content-wrapper {
            padding: 5px;
        }

        .note-list {
            padding: 10px;
        }

        .note-item {
            padding: 10px;
        }

        .dropdown-toggle {
            padding: 8px;
        }

        .editor-container {
            padding: 10px;
        }

        .ql-toolbar.ql-snow {
            padding: 8px !important;
        }

        .ql-toolbar button {
            width: 36px !important;
            height: 36px !important;
            padding: 8px !important;
        }

        .close-btn {
            width: 36px;
            height: 36px;
            font-size: 20px;
        }

        .new-note-btn {
            padding: 10px;
            right: 15px;
            bottom: 15px;
            width: 48px;
            height: 48px;
        }

        .new-note-btn i {
            font-size: 20px;
        }

        .arrow-pointer {
            bottom: 70px;
            right: 28px;
            font-size: 2em;
        }

        .empty-state-message {
            font-size: 14px;
            margin-bottom: 40px;
        }
    }

    /* Touch-optimalizált stílusok */
    @media (hover: none) and (pointer: coarse) {
        .note-item,
        .dropdown-toggle,
        .dropdown-item,
        .save-btn,
        .new-note-btn,
        .close-btn {
            min-height: 44px;
        }

        .dropdown-item {
            padding: 12px 15px;
        }

        .ql-toolbar button,
        .ql-picker {
            min-width: 44px;
            min-height: 44px;
        }
    }

    /* Eszköztár optimalizálása */
    .ql-toolbar.ql-snow {
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 1;
    }

    /* Jegyzetlista görgetés optimalizálása */
    .note-list {
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
    }

    /* Aktív állapotok optimalizálása érintőképernyőkhöz */
    @media (hover: none) {
        .note-item:active,
        .dropdown-item:active,
        .save-btn:active,
        .new-note-btn:active {
            transform: scale(0.98);
        }
    }

    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background-color: white;
        min-width: 160px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 8px;
        z-index: 1000;
        overflow: hidden;
    }

    .dropdown-content.show {
        display: block;
    }

    .dropdown-toggle {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 5px;
        transition: color 0.2s;
        z-index: 2;
    }

    .dropdown-toggle:hover {
        color: #0d6efd;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 15px;
        color: #212529;
        text-decoration: none;
        cursor: pointer;
        transition: background-color 0.2s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        white-space: nowrap;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    .dropdown-item.delete {
        color: #dc3545;
    }

    .dropdown-item.delete:hover {
        background-color: #dc354510;
    }

    .auto-save-notification {
        position: fixed;
        top: 80px;
        right: 20px;
        background-color: #28a745;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        display: none;
        z-index: 1000;
        animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-out 2s forwards;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes fadeOut {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
        }
    }

    /* Empty state message */
    .empty-state-message {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        color: #6c757d;
        font-size: 1.2em;
        display: none;
        z-index: 10;
        width: 90%;
        max-width: 400px;
    }

    /* Arrow pointing to new note button */
    .arrow-pointer {
        display: none;
        position: fixed;
        bottom: 100px;
        right: 45px;
        color: #0d6efd;
        font-size: 3em;
        z-index: 10;
        animation: floatWithButton 2s infinite;
    }

    .new-note-btn {
        position: fixed;
        bottom: 20px;
        right: 30px;
        background: #0d6efd;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 100;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .new-note-btn.highlight {
        animation: floatWithButton 2s infinite;
    }

    @keyframes floatWithButton {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    /* Blinking animation for new note button */
    @keyframes buttonGlow {
        0% { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        50% { box-shadow: 0 4px 20px rgba(13, 110, 253, 0.4); }
        100% { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    }

    .delete-confirmation-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 15px;
    }

    .delete-confirmation-content {
        background: white;
        padding: 30px;
        border-radius: 15px;
        max-width: 500px;
        width: 95%;
        position: relative;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .delete-confirmation-content h2 {
        color: #dc3545;
        margin: 0 0 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .delete-confirmation-content h2 i {
        font-size: 24px;
    }

    .note-details {
        margin: 20px 0;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .note-details p {
        margin: 8px 0;
        color: #495057;
        display: flex;
        align-items: baseline;
    }

    .note-details .label {
        font-weight: bold;
        color: #212529;
        min-width: 100px;
    }

    .note-details .date {
        color: #6c757d;
        font-size: 0.9em;
        margin-left: 5px;
    }

    .delete-confirmation-buttons {
        display: flex;
        gap: 10px;
        margin-top: 25px;
        justify-content: flex-end;
    }

    .btn-cancel {
        background: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-cancel:hover {
        background: #5a6268;
    }

    .btn-delete {
        background: #dc3545;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-delete:hover {
        background: #c82333;
    }

    .context-menu {
        position: fixed;
        background-color: white;
        min-width: 160px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 8px;
        z-index: 1000;
        overflow: hidden;
    }

    .context-menu .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 15px;
        color: #212529;
        text-decoration: none;
        cursor: pointer;
        transition: background-color 0.2s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .context-menu .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    .context-menu .dropdown-item.delete {
        color: #dc3545;
    }

    .context-menu .dropdown-item.delete:hover {
        background-color: #dc354510;
    }

    .note-actions {
        position: absolute;
        right: 50px;
        top: 50%;
        transform: translateY(-50%);
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
    }

    .note-actions.show {
        display: flex;
        gap: 5px;
        padding: 5px;
    }

    .action-button {
        background: none;
        border: none;
        padding: 8px 15px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #212529;
        transition: all 0.2s;
        border-radius: 6px;
    }

    .action-button:hover {
        background-color: #f8f9fa;
    }

    .action-button.delete {
        color: #dc3545;
    }

    .action-button.delete:hover {
        background-color: #dc354510;
    }

    /* Magyar fejléc címek */
    .ql-snow .ql-picker.ql-header .ql-picker-label::before {
        content: 'Normál' !important;
    }

    .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="1"]::before {
        content: 'Címsor 1' !important;
    }

    .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="2"]::before {
        content: 'Címsor 2' !important;
    }

    .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="3"]::before {
        content: 'Címsor 3' !important;
    }

    /* Magyar fejléc címek a legördülő menüben */
    .ql-snow .ql-picker.ql-header .ql-picker-item::before {
        content: 'Normál' !important;
    }

    .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="1"]::before {
        content: 'Címsor 1' !important;
    }

    .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="2"]::before {
        content: 'Címsor 2' !important;
    }

    .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="3"]::before {
        content: 'Címsor 3' !important;
    }

    /* Custom notification styles */
    .custom-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 10px;
        padding: 16px 24px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 9999;
        max-width: 400px;
        transform: translateX(150%);
        transition: transform 0.3s ease-in-out;
        border-left: 4px solid;
    }

    .custom-notification.info {
        border-left-color: #0d6efd;
    }

    .custom-notification.error {
        border-left-color: #dc3545;
    }

    .custom-notification.success {
        border-left-color: #198754;
    }

    .custom-notification.warning {
        border-left-color: #ffc107;
    }

    .custom-notification.show {
        transform: translateX(0);
    }

    .custom-notification i {
        font-size: 20px;
    }

    .custom-notification.info i {
        color: #0d6efd;
    }

    .custom-notification.error i {
        color: #dc3545;
    }

    .custom-notification.success i {
        color: #198754;
    }

    .custom-notification.warning i {
        color: #ffc107;
    }

    .custom-notification-content {
        flex: 1;
    }

    .custom-notification-title {
        font-weight: 600;
        margin-bottom: 4px;
        color: #1e2a38;
    }

    .custom-notification-message {
        color: #6c757d;
        font-size: 14px;
        margin: 0;
    }

    .custom-notification-close {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 4px;
        font-size: 18px;
        transition: color 0.2s;
    }

    .custom-notification-close:hover {
        color: #1e2a38;
    }

    @media (max-width: 768px) {
        .custom-notification {
            top: auto;
            bottom: 20px;
            left: 20px;
            right: 20px;
            max-width: none;
            transform: translateY(150%);
        }

        .custom-notification.show {
            transform: translateY(0);
        }
    }
</style>

<div class="auto-save-notification" id="autoSaveNotification">
    <i class="fas fa-check-circle"></i> <span id="saveNotificationText">Automatikus mentés...</span>
</div>

<h1 style="text-align: center; margin: 20px 0; color: #1e2a38;"><?php echo translate('Jegyzetek'); ?></h1>
<div class="notes-container">
    <div class="empty-state-message" id="emptyStateMessage">
        <?php echo translate('Hozzon létre egy új jegyzetet az alábbi gombra kattintva.'); ?>
    </div>
    <div class="arrow-pointer" id="arrowPointer">↓</div>
    <div class="content-wrapper">
        <div class="note-list" id="notesList">
            <!-- Jegyzetek itt jelennek meg -->
        </div>

        <div class="editor-container" id="editorContainer">
            <button class="close-btn" onclick="closeEditor()">×</button>
            <div class="editor-content">
                <input type="text" id="titleInput" placeholder="<?php echo translate('Jegyzet címe'); ?>">
                <div id="editor"></div>
            </div>
            <div class="button-container">
                <button class="save-btn" onclick="saveNote()">
                    <i class="fas fa-save save-icon"></i>
                    <i class="fas fa-check success-icon"></i>
                    <?php echo translate('Mentés'); ?>
                </button>
            </div>
        </div>
    </div>

    <button class="new-note-btn" onclick="showEditor()">
        <i class="fas fa-plus"></i>
        <span class="btn-text"><?php echo translate('Új jegyzet'); ?></span>
    </button>
</div>

<!-- Delete confirmation modal -->
<div class="delete-confirmation-modal" id="deleteConfirmationModal">
    <div class="delete-confirmation-content">
        <h2><i class="fas fa-trash-alt"></i> <?php echo translate('Biztosan törli?'); ?></h2>
        <div class="note-details">
            <p>
                <span class="label"><?php echo translate('Cím:'); ?></span> 
                <span id="deleteNoteTitle"></span>
            </p>
            <p>
                <span class="label"><?php echo translate('Létrehozva:'); ?></span>
                <span id="deleteNoteCreated"></span>
                <span class="date" id="deleteNoteCreatedTime"></span>
            </p>
            <p id="deleteNoteUpdatedContainer" style="display: none;">
                <span class="label"><?php echo translate('Módosítva:'); ?></span>
                <span id="deleteNoteUpdated"></span>
                <span class="date" id="deleteNoteUpdatedTime"></span>
            </p>
        </div>
        <p><?php echo translate('Ez a művelet nem vonható vissza, és ez a jegyzet törlésre kerül!'); ?></p>
        <div class="delete-confirmation-buttons">
            <button class="btn-cancel" onclick="cancelDelete()"><?php echo translate('Mégse'); ?></button>
            <button class="btn-delete" onclick="confirmDelete()"><?php echo translate('Törlés'); ?></button>
        </div>
    </div>
</div>

<!-- Context menu -->
<div id="contextMenu" class="context-menu"></div>

<div id="notificationContainer"></div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    // Fordítások átadása a JavaScript számára
    const translations = {
        noteTitle: '<?php echo translate("Jegyzet címe"); ?>',
        noteContent: '<?php echo translate("Jegyzet tartalma..."); ?>',
        edit: '<?php echo translate("Szerkesztés"); ?>',
        delete: '<?php echo translate("Törlés"); ?>',
        pleaseEnterTitle: '<?php echo translate("Kérem adjon meg egy címet a jegyzetnek!"); ?>',
        noteCreated: '<?php echo translate("Jegyzet létrehozva!"); ?>',
        noteSaved: '<?php echo translate("Jegyzet mentve!"); ?>',
        errorSaving: '<?php echo translate("Hiba történt a mentés során:"); ?>',
        uploadingImage: '<?php echo translate("Kép feltöltése..."); ?>',
        imageUploadError: '<?php echo translate("Hiba történt a kép feltöltése során"); ?>'
    };

    let quill;
    let currentNoteId = null;
    let autoSaveTimeout = null;
    let isSaving = false;
    let saveTimeout = null;
    let lastEditTime = null;
    let autoSaveInterval = null;
    let lastContent = null;
    let lastTitle = null;
    let noteToDelete = null;

    // ESC gomb figyelése - globális szinten
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const editorContainer = document.getElementById('editorContainer');
            if (editorContainer && editorContainer.classList.contains('show')) {
                closeEditor();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Create and prepare the editor container first
        const editorContainer = document.getElementById('editor');
        if (!editorContainer) return;

        // Pre-configure the container
        editorContainer.style.height = '100%';
        editorContainer.style.minHeight = '200px';
        
        // Create a wrapper div for the editor content
        const editorContent = document.createElement('div');
        editorContent.className = 'editor-content';
        editorContainer.appendChild(editorContent);

        // Saját scroll kezelő létrehozása
        class CustomScroll extends Quill.import('blots/scroll') {
            constructor(registry, domNode, options = {}) {
                super(registry, domNode, options);
                this.setupMutationObserver();
            }

            setupMutationObserver() {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'childList' || mutation.type === 'characterData') {
                            this.update();
                        }
                    });
                });

                observer.observe(this.domNode, {
                    childList: true,
                    subtree: true,
                    characterData: true
                });
            }
        }

        // Regisztráljuk a saját scroll kezelőt
        Quill.register('blots/scroll', CustomScroll, true);

        const toolbarOptions = {
            container: [
                [{ 'header': [false, 1, 2, 3] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': ['', 'center', 'right', 'justify'] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        };

        // Initialize Quill with optimized configuration
        quill = new Quill(editorContent, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: toolbarOptions.container,
                    handlers: {
                        'image': imageHandler
                    }
                },
                clipboard: {
                    matchVisual: false
                },
                history: {
                    delay: 1000,
                    maxStack: 500,
                    userOnly: true
                }
            },
            placeholder: '<?php echo translate('Írja be a jegyzet tartalmát...'); ?>',
            bounds: editorContainer,
            readOnly: false,
            formats: [
                'header',
                'bold', 'italic', 'underline', 'strike',
                'color', 'background',
                'align',
                'list', 'bullet',
                'link', 'image'
            ],
            scrollingContainer: editorContainer
        });

        // Szövegváltozás figyelése optimalizált módon
        let changeTimeout;
        const handleChange = () => {
            if (changeTimeout) clearTimeout(changeTimeout);
            changeTimeout = setTimeout(() => {
                lastEditTime = new Date();
                startAutoSave();
            }, 100);
        };

        quill.on('text-change', handleChange);
        
        // Automatikus görgetés kezelése
        const scrollToBottom = () => {
            const editorElement = quill.container.querySelector('.ql-editor');
            if (editorElement) {
                editorElement.scrollTop = editorElement.scrollHeight;
            }
        };

        // Figyelő a tartalom változásokra
        const contentObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    scrollToBottom();
                }
            });
        });

        const editor = quill.container.querySelector('.ql-editor');
        if (editor) {
            contentObserver.observe(editor, {
                childList: true,
                subtree: true,
                characterData: true
            });
        }

        // Load notes after everything is initialized
        loadNotes();

        // Add input listener for title
        document.getElementById('titleInput').addEventListener('input', handleChange);

        // Tooltipek beállítása
        const tooltips = {
            '.ql-bold': 'Félkövér',
            '.ql-italic': 'Dőlt',
            '.ql-underline': 'Aláhúzott',
            '.ql-strike': 'Áthúzott',
            '.ql-color .ql-picker-label': 'Szövegszín',
            '.ql-background .ql-picker-label': 'Háttérszín',
            '.ql-link': 'Link beszúrása',
            '.ql-image': 'Kép beszúrása',
            '.ql-clean': 'Formázás törlése',
            '.ql-list[value="ordered"]': 'Számozott lista',
            '.ql-list[value="bullet"]': 'Felsorolás'
        };

        // Tooltipek hozzáadása
        Object.entries(tooltips).forEach(([selector, text]) => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                element.removeAttribute('title');
                element.setAttribute('data-tooltip', text);
            });
        });
    });

    function showEditor(note = null) {
        const editorContainer = document.querySelector('.editor-container');
        const emptyStateMessage = document.getElementById('emptyStateMessage');
        const titleInput = document.getElementById('titleInput');
        
        // Elrejtjük az üres állapot üzenetet
        if (emptyStateMessage) {
            emptyStateMessage.style.display = 'none';
        }

        // Megjelenítjük a szerkesztőt
        editorContainer.style.display = 'flex';
        
        // Ha van note, betöltjük az adatait
        if (note) {
            currentNoteId = note.id;
            titleInput.value = note.title;
            quill.root.innerHTML = note.content;
        } else {
            currentNoteId = null;
            titleInput.value = '';
            quill.root.innerHTML = '';
        }

        lastContent = quill.root.innerHTML;
        lastTitle = titleInput.value;
        
        // Fókusz a címre
        titleInput.focus();
    }

    function createNoteElement(note) {
        const noteDiv = document.createElement('div');
        noteDiv.className = 'note-card';
        noteDiv.innerHTML = `
            <div class="note-content">
                <h3>${note.title}</h3>
                <p>${note.content.length > 100 ? note.content.substring(0, 100) + '...' : note.content}</p>
            </div>
        `;
        
        noteDiv.addEventListener('click', () => {
            showEditor(note);
        });

        return noteDiv;
    }

    function loadNotes() {
        fetch('../../includes/api/get_notes.php')
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Server Error Response:', text);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.text().then(text => {
                    try {
                        // Log the raw response for debugging
                        console.log('Raw server response:', text);
                        const data = JSON.parse(text);
                        return data;
                    } catch (e) {
                        console.error('JSON Parse Error. Raw response:', text);
                        throw new Error('Server returned invalid JSON');
                    }
                });
            })
            .then(data => {
                // Validate response structure
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid response format');
                }

                // Handle both direct array and wrapped object formats
                const notes = Array.isArray(data) ? data : (data.data || []);
                
                const notesContainer = document.getElementById('notesList');
                const emptyStateMessage = document.getElementById('emptyStateMessage');
                const newNoteBtn = document.querySelector('.new-note-btn');
                const arrowPointer = document.getElementById('arrowPointer');
                
                notesContainer.innerHTML = '';
                
                // Üres állapot kezelése
                if (notes.length === 0) {
                    emptyStateMessage.style.display = 'block';
                    newNoteBtn.classList.add('highlight');
                    arrowPointer.style.display = 'block';
                    return;
                }

                emptyStateMessage.style.display = 'none';
                newNoteBtn.classList.remove('highlight');
                arrowPointer.style.display = 'none';
                
                notes.forEach(note => {
                    const noteItem = document.createElement('div');
                    noteItem.className = 'note-item';
                    noteItem.setAttribute('data-id', note.id);
                    if (currentNoteId === note.id) {
                        noteItem.classList.add('active');
                    }
                    
                    const safeNote = {
                        id: note.id,
                        title: note.title,
                        created_at: note.created_at,
                        updated_at: note.updated_at || note.created_at
                    };
                    
                    noteItem.innerHTML = `
                        <h3 class="note-title">${note.title}</h3>
                        <div class="dropdown">
                            <button class="dropdown-toggle" onclick="toggleDropdown(${note.id})">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-content">
                                <button class="dropdown-item" onclick="editNote(${note.id}, event)">
                                    <i class="fas fa-edit"></i> Szerkesztés
                                </button>
                                <button class="dropdown-item delete" onclick="event.stopPropagation(); showDeleteConfirmation({
                                    id: ${note.id},
                                    title: '${note.title.replace(/'/g, "\\'")}',
                                    created_at: '${safeNote.created_at}',
                                    updated_at: '${safeNote.updated_at}'
                                })">
                                    <i class="fas fa-trash-alt"></i> Törlés
                                </button>
                            </div>
                        </div>
                        <div class="note-actions" data-id="${note.id}">
                            <button class="action-button" onclick="editNote(${note.id}, event)">
                                <i class="fas fa-edit"></i> Szerkesztés
                            </button>
                            <button class="action-button delete" onclick="event.stopPropagation(); showDeleteConfirmation({
                                id: ${note.id},
                                title: '${note.title.replace(/'/g, "\\'")}',
                                created_at: '${safeNote.created_at}',
                                updated_at: '${safeNote.updated_at}'
                            })">
                                <i class="fas fa-trash-alt"></i> Törlés
                            </button>
                        </div>
                    `;
                    
                    // Jobb klikk eseménykezelő
                    noteItem.addEventListener('contextmenu', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        hideAllNoteActions(); // Először elrejtjük az összes gombot
                        const actions = noteItem.querySelector('.note-actions');
                        actions.classList.add('show');
                    });
                    
                    noteItem.querySelector('.note-title').addEventListener('click', () => editNote(note.id));
                    notesContainer.appendChild(noteItem);
                });

                // Context menu hozzáadása a DOM-hoz
                if (!document.getElementById('contextMenu')) {
                    const contextMenu = document.createElement('div');
                    contextMenu.id = 'contextMenu';
                    contextMenu.className = 'context-menu';
                    document.body.appendChild(contextMenu);
                }
            })
            .catch(error => {
                console.error('Hiba történt a jegyzetek betöltésekor:', error);
                const notesContainer = document.getElementById('notesList');
                notesContainer.innerHTML = `
                    <div class="alert alert-danger" style="padding: 15px; margin: 10px; border-radius: 4px; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">
                        <strong>Hiba történt:</strong> ${error.message}
                        <br>Kérjük, próbálja újra később vagy jelentkezzen be újra.
                        <br><small>Ha a probléma továbbra is fennáll, kérjük, értesítse a rendszergazdát.</small>
                    </div>
                `;
            });
    }

    function editNote(noteId, event) {
        if (event) {
            event.stopPropagation();
        }
        
        hideAllNoteActions();
        
        fetch('../../includes/api/get_note.php?id=' + noteId)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Server Error Response:', text);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON Parse Error. Raw response:', text);
                        throw new Error('Server returned invalid JSON');
                    }
                });
            })
            .then(response => {
                if (!response.success) {
                    throw new Error(response.error || 'Unknown error');
                }
                const note = response.data;
                if (!note || !note.id) {
                    throw new Error('Invalid note data received');
                }
                showEditor(note);
                document.querySelectorAll('.note-item').forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('data-id') == noteId) {
                        item.classList.add('active');
                    }
                });
            })
            .catch(error => {
                console.error('Hiba történt a jegyzet betöltésekor:', error);
                alert('Hiba történt a jegyzet betöltése során: ' + error.message);
            });
    }

    // Segédfüggvény az összes note-actions elrejtéséhez
    function hideAllNoteActions() {
        document.querySelectorAll('.note-actions').forEach(actions => {
            actions.classList.remove('show');
        });
    }

    function startAutoSave() {
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
        }

        const checkChanges = () => {
            const now = new Date();
            if (!lastEditTime) return;

            const timeSinceLastEdit = now - lastEditTime;
            if (timeSinceLastEdit >= 3000 && !isSaving) {
                const title = document.getElementById('titleInput').value;
                const content = quill.root.innerHTML;

                if (title !== lastTitle || content !== lastContent) {
                    autoSave();
                }
            }
        };

        autoSaveInterval = setInterval(checkChanges, 1000);
    }

    function autoSave() {
        const title = document.getElementById('titleInput').value;
        const content = quill.root.innerHTML;

        if (!title || isSaving) {
            return;
        }

        isSaving = true;
        const formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        if (currentNoteId !== null) {
            formData.append('id', currentNoteId);
        }

        fetch('../../includes/api/save_note.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (!currentNoteId) {
                    currentNoteId = data.id;
                }
                showAutoSaveNotification();
                loadNotes();
                lastContent = content;
                lastTitle = title;
                console.log('Automatikus mentés sikeres');
            } else {
                throw new Error(data.error || 'Ismeretlen hiba történt');
            }
        })
        .catch(error => {
            console.error('Automatikus mentés hiba:', error);
        })
        .finally(() => {
            isSaving = false;
        });
    }

    function showNotification(type, title, message, duration = 3000) {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        
        let icon;
        switch(type) {
            case 'info':
                icon = 'fa-info-circle';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                break;
            case 'success':
                icon = 'fa-check-circle';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                break;
        }

        notification.innerHTML = `
            <i class="fas ${icon}"></i>
            <div class="custom-notification-content">
                <div class="custom-notification-title">${title}</div>
                <p class="custom-notification-message">${message}</p>
            </div>
            <button class="custom-notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(notification);
        
        // Trigger reflow to enable animation
        notification.offsetHeight;
        notification.classList.add('show');

        // Auto remove after duration
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    function saveNote() {
        const title = document.getElementById('titleInput').value;
        const content = quill.root.innerHTML;
        const saveBtn = document.querySelector('.save-btn');

        if (!title.trim()) {
            showNotification(
                'warning',
                'Hiányzó cím',
                'Kérem adjon meg egy címet a jegyzetnek!',
                4000
            );
            return;
        }

        // Ha már folyamatban van mentés, ne engedjük az újat
        if (isSaving) {
            return;
        }

        // Ellenőrizzük, hogy történt-e változás
        if (lastContent === content && lastTitle === title) {
            showNotification(
                'info',
                'Nincs változás',
                'A jegyzet már el van mentve!',
                2000
            );
            return;
        }

        isSaving = true;
        saveBtn.disabled = true;
        saveBtn.classList.add('saving');
        
        const formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        
        if (currentNoteId !== null) {
            formData.append('id', currentNoteId);
        }

        fetch('../../includes/api/save_note.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Hálózati hiba történt');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (!currentNoteId) {
                    currentNoteId = data.id;
                    showNotification('success', 'Siker', 'Jegyzet létrehozva!');
                } else {
                    showNotification('success', 'Siker', 'Jegyzet mentve!');
                }
                loadNotes();
                lastContent = content;
                lastTitle = title;
                
                // Sikeres mentés animáció
                saveBtn.classList.remove('saving');
                saveBtn.classList.add('success');
                setTimeout(() => {
                    saveBtn.classList.remove('success');
                }, 1000);
            } else {
                throw new Error(data.error || 'Ismeretlen hiba történt');
            }
        })
        .catch(error => {
            console.error('Mentési hiba:', error);
            // Csak hálózati hiba esetén mutatunk hibaüzenetet
            if (error.message === 'Hálózati hiba történt') {
                showNotification(
                    'error',
                    'Kapcsolati hiba',
                    'Nem sikerült kapcsolódni a szerverhez. Kérjük, ellenőrizze az internetkapcsolatát!',
                    5000
                );
            }
        })
        .finally(() => {
            setTimeout(() => {
                isSaving = false;
                saveBtn.disabled = false;
                saveBtn.classList.remove('saving');
            }, 500); // Kis késleltetés a jobb UX érdekében
        });
    }

    function showAutoSaveNotification() {
        const notification = document.getElementById('autoSaveNotification');
        notification.style.display = 'block';
        
        // Az animáció újraindításához eltávolítjuk és újra hozzáadjuk az elemet
        notification.style.animation = 'none';
        notification.offsetHeight; // Trigger reflow
        notification.style.animation = 'slideIn 0.3s ease-out, fadeOut 0.3s ease-out 2s forwards';

        // 2.5 másodperc után elrejtjük a notifikációt
        setTimeout(() => {
            notification.style.display = 'none';
        }, 2500);
    }

    function deleteNote(noteId) {
        const formData = new FormData();
        formData.append('id', noteId);

        fetch('../../includes/api/delete_note.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotes();
            } else {
                console.error('Hiba történt a jegyzet törlésekor:', data.error);
            }
        })
        .catch(error => {
            console.error('Hiba történt a jegyzet törlésekor:', error);
        });
    }

    function viewNote(noteId) {
        if (currentNoteId === noteId) {
            closeEditor();
            return;
        }

        fetch(`../../includes/api/get_note.php?id=${noteId}`)
            .then(response => response.json())
            .then(note => {
                const editorContainer = document.getElementById('editorContainer');
                document.getElementById('titleInput').value = note.title;
                quill.root.innerHTML = note.content;
                
                // Mentjük az eredeti értékeket
                lastContent = note.content;
                lastTitle = note.title;
                lastEditTime = null; // Reseteljük az utolsó szerkesztés idejét
                
                // Enable both title and content editing
                document.getElementById('titleInput').disabled = false;
                quill.enable();
                
                // Show save button for changes
                document.querySelector('.save-btn').style.display = 'inline-block';
                
                editorContainer.classList.add('show');
                currentNoteId = noteId;
                
                // Aktív jegyzet kijelölése
                document.querySelectorAll('.note-item').forEach(item => {
                    item.classList.remove('active');
                });
                const activeNote = document.querySelector(`.note-item[data-id="${noteId}"]`);
                if (activeNote) {
                    activeNote.classList.add('active');
                }
            })
            .catch(error => {
                console.error('Hiba történt a jegyzet betöltésekor:', error);
            });
    }

    function closeEditor() {
        const editorContainer = document.getElementById('editorContainer');
        
        // Először eltávolítjuk a show osztályt
        editorContainer.classList.remove('show');
        // Majd elrejtjük az elemet
        editorContainer.style.display = 'none';
        
        // Eltávolítjuk a kijelölést az aktív jegyzetről
        const activeNote = document.querySelector('.note.active');
        if (activeNote) {
            activeNote.classList.remove('active');
        }
        
        // Reset states
        currentNoteId = null;
        lastEditTime = null;
        lastContent = null;
        lastTitle = null;
        
        // Clear any existing intervals
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
            autoSaveInterval = null;
        }
    }

    function toggleDropdown(noteId) {
        event.stopPropagation(); // Megakadályozzuk a jegyzet kiválasztását
        const dropdown = document.querySelector(`.note-item[data-id="${noteId}"] .dropdown-content`);
        
        // Minden más dropdown bezárása
        document.querySelectorAll('.dropdown-content').forEach(dc => {
            if (dc !== dropdown) {
                dc.classList.remove('show');
            }
        });
        
        dropdown.classList.toggle('show');
    }

    // Dropdown bezárása kattintásra bárhol máshol
    document.addEventListener('click', function(event) {
        if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-content')) {
            document.querySelectorAll('.dropdown-content').forEach(dc => {
                dc.classList.remove('show');
            });
        }
    });

    // Dokumentum kattintás eseménykezelő
    document.addEventListener('click', function(event) {
        // Ha nem a note-actions-re vagy annak gyermekeire kattintottunk
        if (!event.target.closest('.note-actions') && !event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-content')) {
            hideAllNoteActions();
        }
    });

    // Dokumentum jobb klikk eseménykezelő
    document.addEventListener('contextmenu', function(event) {
        if (!event.target.closest('.note-item')) {
            hideAllNoteActions();
        }
    });

    function showDeleteConfirmation(note) {
        noteToDelete = note;
        const modal = document.getElementById('deleteConfirmationModal');
        document.getElementById('deleteNoteTitle').textContent = note.title;
        
        // Dátumok formázása
        const createdDate = new Date(note.created_at);
        const updatedDate = new Date(note.updated_at);
        const dateOptions = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        };
        const timeOptions = {
            hour: '2-digit',
            minute: '2-digit'
        };
        
        // Létrehozás dátuma és ideje
        document.getElementById('deleteNoteCreated').textContent = createdDate.toLocaleDateString('hu-HU', dateOptions);
        document.getElementById('deleteNoteCreatedTime').textContent = createdDate.toLocaleTimeString('hu-HU', timeOptions);
        
        // Módosítás dátuma és ideje (ha van)
        const updatedElement = document.getElementById('deleteNoteUpdatedContainer');
        if (note.updated_at && note.updated_at !== note.created_at) {
            updatedElement.style.display = 'flex';
            document.getElementById('deleteNoteUpdated').textContent = updatedDate.toLocaleDateString('hu-HU', dateOptions);
            document.getElementById('deleteNoteUpdatedTime').textContent = updatedDate.toLocaleTimeString('hu-HU', timeOptions);
        } else {
            updatedElement.style.display = 'none';
        }
        
        modal.style.display = 'flex';
    }

    function cancelDelete() {
        const modal = document.getElementById('deleteConfirmationModal');
        modal.style.display = 'none';
        noteToDelete = null;
    }

    function confirmDelete() {
        if (noteToDelete) {
            deleteNote(noteToDelete.id);
            cancelDelete();
        }
    }

    // Kép feltöltés kezelő függvény
    function imageHandler() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();

        input.onchange = async () => {
            const file = input.files[0];
            if (file) {
                try {
                    const formData = new FormData();
                    formData.append('image', file);

                    // Loading állapot mutatása
                    const range = quill.getSelection(true);
                    quill.insertText(range.index, 'Kép feltöltése...', 'bold', true);
                    const loadingPosition = range.index;

                    const response = await fetch('../../includes/api/upload_image.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    // Töröljük a loading szöveget
                    quill.deleteText(loadingPosition, 'Kép feltöltése...'.length);

                    if (result.success) {
                        // Teljes URL létrehozása a relatív URL-ből
                        const baseUrl = window.location.origin;
                        const fullImageUrl = baseUrl + result.url;
                        
                        // Beszúrjuk a képet
                        quill.insertEmbed(loadingPosition, 'image', fullImageUrl, 'user');
                        // Kurzor mozgatása a kép után
                        quill.setSelection(loadingPosition + 1);
                        
                        // Frissítjük a lastContent és lastEditTime értékeket
                        lastContent = quill.root.innerHTML;
                        lastEditTime = new Date();
                    } else {
                        throw new Error(result.error || 'Hiba történt a kép feltöltése során');
                    }
                } catch (error) {
                    console.error('Kép feltöltési hiba:', error);
                    alert('Hiba történt a kép feltöltése során: ' + error.message);
                }
            }
        };
    }

    // Context menu megjelenítése
    function showContextMenu(event, note) {
        const contextMenu = document.getElementById('contextMenu');
        contextMenu.innerHTML = `
            <button class="dropdown-item" onclick="editNote(${note.id}, event)">
                <i class="fas fa-edit"></i> Szerkesztés
            </button>
            <button class="dropdown-item delete" onclick="showDeleteConfirmation({
                id: ${note.id},
                title: '${note.title.replace(/'/g, "\\'")}',
                created_at: '${note.created_at}',
                updated_at: '${note.updated_at}'
            })">
                <i class="fas fa-trash-alt"></i> Törlés
            </button>
        `;
        
        contextMenu.style.display = 'block';
        contextMenu.style.left = event.pageX + 'px';
        contextMenu.style.top = event.pageY + 'px';
        
        // Context menu bezárása kattintásra bárhol máshol
        document.addEventListener('click', hideContextMenu);
        document.addEventListener('contextmenu', hideContextMenu);
    }

    // Context menu elrejtése
    function hideContextMenu() {
        const contextMenu = document.getElementById('contextMenu');
        if (contextMenu) {
            contextMenu.style.display = 'none';
        }
        document.removeEventListener('click', hideContextMenu);
        document.removeEventListener('contextmenu', hideContextMenu);
    }

    // Kattintás eseménykezelő a dokumentumra a note-actions elrejtéséhez
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.note-actions') && !event.target.closest('.note-item')) {
            document.querySelectorAll('.note-actions').forEach(actions => {
                actions.classList.remove('show');
            });
        }
    });

    // Dokumentum kattintás eseménykezelő
    document.addEventListener('mousedown', function(event) {
        // Ha nem a note-actions-re vagy annak gyermekeire kattintottunk
        if (!event.target.closest('.note-actions') && !event.target.closest('.dropdown-content')) {
            // Minden note-actions elrejtése
            document.querySelectorAll('.note-actions').forEach(actions => {
                actions.classList.remove('show');
            });
        }
    });
</script>

<?php require_once '../../includes/layout/footer.php'; ?>