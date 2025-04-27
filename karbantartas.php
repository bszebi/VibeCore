// Update the fetch code for broken stuff
fetch('get_broken_stuff.php', {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    }
})
.then(async response => {
    const data = await response.json();
    if (!data.success) {
        throw new Error(data.message || 'Server error');
    }
    return data.data; // Return the actual array of items
})
.then(data => {
    console.log('Parsed broken stuff data:', data);
    if (!Array.isArray(data)) {
        console.warn('Response is not an array:', data);
        return updateBrokenList([]);
    }
    updateBrokenList(data);
})
.catch(error => {
    console.error('Broken stuff fetch error:', error);
    console.error('Error stack:', error.stack);
    showNotification('Hiba történt a hibás eszközök lekérésekor!', 'error');
    updateBrokenList([]);
}); 