import React, { useState, useEffect } from "react";
import ReactDOM from "react-dom";

const AssignmentsApp = () => {
    const [assignments, setAssignments] = useState([]);
    const [newAssignment, setNewAssignment] = useState({ title: "", main_keyword: "" });
    const [loading, setLoading] = useState(false);

    // Fetch Assignments
    useEffect(() => {
        fetchAssignments();
    }, []);

    const fetchAssignments = async () => {
        setLoading(true);
        try {
            const response = await fetch(`${StoryFlowData.restUrl}`, {
                headers: {
                    "X-WP-Nonce": StoryFlowData.nonce,
                },
            });
            const data = await response.json();
            setAssignments(data);
        } catch (error) {
            console.error("Error fetching assignments:", error);
        } finally {
            setLoading(false);
        }
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setNewAssignment((prev) => ({ ...prev, [name]: value }));
    };

    const handleCreateAssignment = async () => {
        try {
            const response = await fetch(`${StoryFlowData.restUrl}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": StoryFlowData.nonce,
                },
                body: JSON.stringify(newAssignment),
            });
            if (response.ok) {
                fetchAssignments();
                setNewAssignment({ title: "", main_keyword: "" });
            }
        } catch (error) {
            console.error("Error creating assignment:", error);
        }
    };

    const handleDeleteAssignment = async (id) => {
        try {
            const response = await fetch(`${StoryFlowData.restUrl}/${id}`, {
                method: "DELETE",
                headers: {
                    "X-WP-Nonce": StoryFlowData.nonce,
                },
            });
            if (response.ok) {
                fetchAssignments();
            }
        } catch (error) {
            console.error("Error deleting assignment:", error);
        }
    };

    return (
        <div className="story-flow-assignments">
            <h1>Assignments</h1>

            {loading && <p>Loading...</p>}

            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Main Keyword</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {assignments.map((assignment) => (
                        <tr key={assignment.id}>
                            <td>{assignment.title}</td>
                            <td>{assignment.main_keyword}</td>
                            <td>
                                <button onClick={() => handleDeleteAssignment(assignment.id)}>
                                    Delete
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <h2>Create New Assignment</h2>
            <input
                type="text"
                name="title"
                placeholder="Title"
                value={newAssignment.title}
                onChange={handleInputChange}
            />
            <input
                type="text"
                name="main_keyword"
                placeholder="Main Keyword"
                value={newAssignment.main_keyword}
                onChange={handleInputChange}
            />
            <button onClick={handleCreateAssignment}>Create</button>
        </div>
    );
};

document.addEventListener("DOMContentLoaded", () => {
    const appElement = document.getElementById("story-flow-assignments");
    if (appElement) {
        ReactDOM.render(<AssignmentsApp />, appElement);
    }
});
