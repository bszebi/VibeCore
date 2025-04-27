class StatusManager {
    constructor() {
        this.statusSelect = document.getElementById('status-select');
        this.statusComment = document.getElementById('status-comment');
        this.updateButton = document.getElementById('update-status-btn');
        this.statusHistory = document.getElementById('status-history');
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        if (this.updateButton) {
            this.updateButton.addEventListener('click', () => this.updateStatus());
        }
    }

    async updateStatus() {
        const statusId = this.statusSelect.value;
        const comment = this.statusComment.value;

        try {
            const response = await fetch('update_user_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status_id: statusId,
                    comment: comment
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('success', data.message);
                this.updateStatusHistory();
                this.statusComment.value = '';
            } else {
                this.showNotification('error', data.message);
            }
        } catch (error) {
            this.showNotification('error', 'An error occurred while updating status');
            console.error('Error:', error);
        }
    }

    async updateStatusHistory() {
        try {
            const response = await fetch('get_status_history.php');
            const data = await response.json();

            if (data.success) {
                this.renderStatusHistory(data.history);
            }
        } catch (error) {
            console.error('Error fetching status history:', error);
        }
    }

    renderStatusHistory(history) {
        if (!this.statusHistory) return;

        this.statusHistory.innerHTML = history.map(entry => `
            <div class="status-history-entry">
                <div class="status-name">${entry.status_name}</div>
                <div class="status-dates">
                    ${this.formatDate(entry.status_startdate)} - 
                    ${entry.status_enddate ? this.formatDate(entry.status_enddate) : 'Present'}
                </div>
                ${entry.comment ? `<div class="status-comment">${entry.comment}</div>` : ''}
            </div>
        `).join('');
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}

// Initialize the status manager when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.statusManager = new StatusManager();
}); 