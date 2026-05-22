import './bootstrap.js';
import '../css/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import AppRouter from './router/index.jsx';

const root = createRoot(document.getElementById('app'));
root.render(
    <BrowserRouter>
        <AppRouter />
    </BrowserRouter>
);
