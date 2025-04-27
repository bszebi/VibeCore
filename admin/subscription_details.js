// Function to create and show subscription details popup
function showSubscriptionDetails(companyId, companyName) {
    console.log('Fetching subscription details for company ID:', companyId);
    console.log('Company name:', companyName);
    
    // Fetch subscription details
    fetch(`subscription_details.php?company_id=${companyId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    // Check if the response contains HTML error messages
                    if (text.includes('<br />') || text.includes('<b>')) {
                        console.error('PHP error in response:', text);
                        throw new Error('Server error occurred. Please check the console for details.');
                    }
                    
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    return data;
                } catch (e) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('Processing data:', data);
            if (data.error) {
                showError('Hiba', data.error);
                return;
            }
            
            const subscription = data.subscription;
            const modifications = data.modifications;
            const payments = data.payments;

            console.log('Subscription:', subscription);
            console.log('Modifications:', modifications);
            console.log('Payments:', payments);

            // Create popup content
            const popupContent = `
                <div class="subscription-details">
                    ${subscription ? `
                        <div class="subscription-card">
                            <div class="card-header">
                                <h2>Előfizetési adatok</h2>
                            </div>
                            <div class="card-content">
                                <div class="info-row">
                                    <strong>Csomag:</strong>
                                    <span>${subscription.plan_name
                                        .replace('_eves', ' - Éves')
                                        .replace('_havi', ' - Havi')
                                        .replace('kozepes', 'Közepes')
                                        .replace('uzleti', 'Üzleti')
                                        .replace('alap', 'Alap')
                                        .replace('free-trial', 'Ingyenes próba időszak')}</span>
                                </div>
                                <div class="info-row">
                                    <strong>A csomagon lett módosítva:</strong>
                                    <span>${modifications.length > 0 ? 'Igen' : 'Nem'}</span>
                                </div>
                                <div class="info-row">
                                    <strong>Leírás:</strong>
                                    <span>${subscription.plan_description}</span>
                                </div>
                                <div class="info-row">
                                    <strong>Státusz:</strong>
                                    <span>${subscription.subscription_status}</span>
                                </div>
                                <div class="info-row">
                                    <strong>Ár:</strong>
                                    <span>${formatPrice(subscription.plan_price)} Ft</span>
                                </div>
                                <div class="info-row">
                                    <strong>Kezdés:</strong>
                                    <span>${formatDate(subscription.start_date)}</span>
                                </div>
                                <div class="info-row">
                                    <strong>Következő fizetés:</strong>
                                    <span>${formatDate(subscription.next_billing_date)}</span>
                                </div>
                            </div>
                        </div>

                        <div class="payment-card">
                            <div class="card-header">
                                <h2>Fizetési előzmények</h2>
                            </div>
                            <div class="card-content">
                                ${payments.length > 0 ? payments.map(payment => `
                                    <div class="payment-item">
                                        <div class="payment-row">
                                            <strong>Dátum:</strong>
                                            <span>${formatDate(payment.payment_date)}</span>
                                        </div>
                                        <div class="payment-row">
                                            <strong>Összeg:</strong>
                                            <span>${formatPrice(payment.amount)} Ft</span>
                                        </div>
                                        <div class="payment-row">
                                            <strong>Státusz:</strong>
                                            <span>${payment.payment_status}</span>
                                        </div>
                                    </div>
                                `).join('') : '<p>Nincsenek fizetési előzmények</p>'}
                            </div>
                        </div>
                    ` : `
                        <div class="no-subscription">
                            <h2>Nincs aktív előfizetés</h2>
                            <p>A cégnek jelenleg nincs aktív előfizetése.</p>
                        </div>
                    `}
                </div>
            `;

            // Create and show popup
            createPopup({
                theme: 'subscription',
                title: `Előfizetési adatok - ${companyName}`,
                content: popupContent,
                width: 600,
                height: 800,
                taskbarTitle: `${companyName} - Előfizetés`
            });
        })
        .catch(error => {
            console.error('Error details:', error);
            showError('Hiba történt az előfizetési adatok lekérdezése során: ' + error.message);
        });
}

// Helper function to format price
function formatPrice(price) {
    return new Intl.NumberFormat('hu-HU').format(price);
}

// Helper function to format date
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('hu-HU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Add subscription button to company details popup
function addSubscriptionButton(companyId) {
    // Get company name from the parent popup header span
    const headerSpan = document.querySelector('.popup[data-theme="company-details"] .popup-header span');
    const companyName = headerSpan ? headerSpan.textContent.trim() : 'Cég';
    
    console.log('Found company name:', companyName); // Debug log
    
    const subscriptionButton = document.createElement('button');
    subscriptionButton.className = 'btn btn-primary';
    subscriptionButton.innerHTML = '<i class="fas fa-box"></i> Csomag';
    subscriptionButton.onclick = () => showSubscriptionDetails(companyId, companyName);
    
    // Insert button before the company members button
    const membersButton = document.querySelector('.popup[data-theme="company-details"] .company-members-btn');
    if (membersButton) {
        membersButton.parentNode.insertBefore(subscriptionButton, membersButton);
    }
} 