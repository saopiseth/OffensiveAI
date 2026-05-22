import React, { useCallback, useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import ReactFlow, {
    addEdge, Background, Controls,
    useNodesState, useEdgesState, MarkerType,
} from 'reactflow';
import 'reactflow/dist/style.css';
import { ArrowLeft, Save, Play, Plus } from 'lucide-react';
import Button from '../../components/ui/Button.jsx';
import RunWorkflowModal from '../../components/ui/RunWorkflowModal.jsx';
import { workflowService } from '../../services/workflow.service.js';
import { skillService } from '../../services/skill.service.js';

const nodeTypes_colors = {
    input: 'bg-blue-500/20 border-blue-500/40 text-blue-300',
    skill: 'bg-violet-500/20 border-violet-500/40 text-violet-300',
    condition: 'bg-yellow-500/20 border-yellow-500/40 text-yellow-300',
    output: 'bg-green-500/20 border-green-500/40 text-green-300',
};

function CustomNode({ data }) {
    const color = nodeTypes_colors[data.type] || nodeTypes_colors.skill;
    return (
        <div className={`px-4 py-3 rounded-lg border min-w-32 text-center ${color}`}>
            <div className="text-xs font-bold uppercase opacity-60 mb-1">{data.type}</div>
            <div className="text-sm font-semibold">{data.label}</div>
            {data.skill_name && <div className="text-xs opacity-70 mt-1">{data.skill_name}</div>}
        </div>
    );
}

const customNodeTypes = { custom: CustomNode };

const defaultEdgeOptions = {
    style: { stroke: '#6d28d9', strokeWidth: 2 },
    markerEnd: { type: MarkerType.ArrowClosed, color: '#6d28d9' },
};

export default function WorkflowBuilder() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [workflow, setWorkflow] = useState(null);
    const [skills, setSkills] = useState([]);
    const [nodes, setNodes, onNodesChange] = useNodesState([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);
    const [saving, setSaving]       = useState(false);
    const [executing, setExecuting] = useState(false);
    const [saved, setSaved]         = useState(false);
    const [runModal, setRunModal]   = useState(false);

    useEffect(() => {
        Promise.all([
            workflowService.get(id),
            skillService.list({ per_page: 100 }),
        ]).then(([wfRes, skillsRes]) => {
            setWorkflow(wfRes.data);
            setSkills(skillsRes.data.data ?? []);
            const graphData = wfRes.data.graph_data;
            if (graphData?.nodes) setNodes(graphData.nodes);
            if (graphData?.edges) setEdges(graphData.edges);
        });
    }, [id]);

    const onConnect = useCallback((params) => setEdges(es => addEdge({ ...params, ...defaultEdgeOptions }, es)), []);

    const addNode = (type, label, extra = {}) => {
        const newNode = {
            id: `node_${Date.now()}`,
            type: 'custom',
            position: { x: 100 + nodes.length * 50, y: 100 + nodes.length * 30 },
            data: { type, label, ...extra },
        };
        setNodes(ns => [...ns, newNode]);
        setSaved(false);
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            await workflowService.update(id, { graph_data: { nodes, edges } });
            setSaved(true);
            setTimeout(() => setSaved(false), 2000);
        } finally { setSaving(false); }
    };

    const handleExecute = async (payload) => {
        setExecuting(true);
        try {
            await handleSave();
            await workflowService.execute(id, payload);
            setRunModal(false);
            navigate('/executions');
        } finally { setExecuting(false); }
    };

    return (
        <div className="flex flex-col h-full -m-6">
            <div className="flex items-center justify-between px-6 py-3 bg-gray-900 border-b border-gray-800">
                <div className="flex items-center gap-3">
                    <button onClick={() => navigate('/workflows')} className="text-gray-400 hover:text-white"><ArrowLeft size={18} /></button>
                    <h1 className="font-semibold text-white">{workflow?.name ?? 'Workflow Builder'}</h1>
                </div>

                <div className="flex items-center gap-2">
                    <div className="flex gap-1 border border-gray-700 rounded-lg p-1">
                        {[
                            { type: 'input', label: 'Input' },
                            { type: 'condition', label: 'Condition' },
                            { type: 'output', label: 'Output' },
                        ].map(({ type, label }) => (
                            <button key={type} onClick={() => addNode(type, label)}
                                className="px-3 py-1 text-xs text-gray-300 hover:text-white hover:bg-gray-700 rounded transition-colors">
                                + {label}
                            </button>
                        ))}
                        <div className="w-px bg-gray-700 mx-1" />
                        <select onChange={e => { if (e.target.value) { const s = skills.find(sk => sk.id === e.target.value); addNode('skill', s.name, { skill_id: s.id, skill_name: s.name }); e.target.value = ''; } }}
                            className="text-xs bg-transparent text-gray-300 hover:text-white px-2 cursor-pointer focus:outline-none">
                            <option value="">+ Skill Node</option>
                            {skills.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </div>
                    <Button variant="ghost" size="sm" onClick={handleSave} loading={saving}>
                        <Save size={14} />{saved ? 'Saved!' : 'Save'}
                    </Button>
                    <Button size="sm" onClick={() => setRunModal(true)}>
                        <Play size={14} /> Execute
                    </Button>
                </div>
            </div>

            <div className="flex-1" style={{ height: 'calc(100vh - 130px)' }}>
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    onNodesChange={onNodesChange}
                    onEdgesChange={onEdgesChange}
                    onConnect={onConnect}
                    nodeTypes={customNodeTypes}
                    defaultEdgeOptions={defaultEdgeOptions}
                    fitView
                    className="bg-gray-950"
                >
                    <Background color="#374151" gap={20} size={1} />
                    <Controls className="[&>button]:bg-gray-800 [&>button]:border-gray-700 [&>button]:text-white [&>button:hover]:bg-gray-700" />
                </ReactFlow>
            </div>

            <RunWorkflowModal
                open={runModal}
                workflow={workflow}
                onClose={() => setRunModal(false)}
                onRun={handleExecute}
                loading={executing}
            />
        </div>
    );
}
