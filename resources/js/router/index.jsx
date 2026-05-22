import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import useAuthStore from '../store/authStore.js';
import Layout from '../components/layout/Layout.jsx';
import Login from '../pages/auth/Login.jsx';
import Register from '../pages/auth/Register.jsx';
import Dashboard from '../pages/Dashboard.jsx';
import UsersList from '../pages/users/UsersList.jsx';
import RolesList from '../pages/roles/RolesList.jsx';
import SkillsList from '../pages/skills/SkillsList.jsx';
import SkillDetail from '../pages/skills/SkillDetail.jsx';
import MirrorSkills from '../pages/skills/MirrorSkills.jsx';
import ConvertSkills from '../pages/skills/ConvertSkills.jsx';
import WorkflowsList from '../pages/workflows/WorkflowsList.jsx';
import WorkflowBuilder from '../pages/workflows/WorkflowBuilder.jsx';
import ExecutionsList from '../pages/executions/ExecutionsList.jsx';
import ExecutionDetail from '../pages/executions/ExecutionDetail.jsx';
import AiSettings from '../pages/settings/AiSettings.jsx';
import ScheduledWorkflows from '../pages/workflows/ScheduledWorkflows.jsx';

function ProtectedRoute({ children }) {
    const token = useAuthStore(s => s.token);
    return token ? children : <Navigate to="/login" replace />;
}

function GuestRoute({ children }) {
    const token = useAuthStore(s => s.token);
    return !token ? children : <Navigate to="/" replace />;
}

export default function AppRouter() {
    return (
        <Routes>
            <Route path="/login" element={<GuestRoute><Login /></GuestRoute>} />
            <Route path="/register" element={<GuestRoute><Register /></GuestRoute>} />
            <Route path="/" element={<ProtectedRoute><Layout /></ProtectedRoute>}>
                <Route index element={<Dashboard />} />
                <Route path="users" element={<UsersList />} />
                <Route path="roles" element={<RolesList />} />
                <Route path="skills" element={<SkillsList />} />
                <Route path="skills/mirror" element={<MirrorSkills />} />
                <Route path="skills/convert" element={<ConvertSkills />} />
                <Route path="skills/:id" element={<SkillDetail />} />
                <Route path="workflows" element={<WorkflowsList />} />
                <Route path="workflows/:id/builder" element={<WorkflowBuilder />} />
                <Route path="executions" element={<ExecutionsList />} />
                <Route path="executions/:id" element={<ExecutionDetail />} />
                <Route path="scheduled-workflows" element={<ScheduledWorkflows />} />
                <Route path="settings/ai" element={<AiSettings />} />
            </Route>
        </Routes>
    );
}
