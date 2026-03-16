<?php
/**
 * Client Form View (Add/Edit)
 */
$db = new Database(getMasterPassword(), Auth::userId());
$id = $_GET['id'] ?? null;
$client = null;

if ($id) {
    $clients = $db->load('clients');
    foreach ($clients as $c) {
        if ($c['id'] === $id) {
            $client = $c;
            break;
        }
    }
}

$title = $id ? 'Edit Client' : 'New Client';

function clientFieldValue(mixed $value): string {
    if (is_array($value)) {
        $flat = array_map(function($item) {
            if (is_scalar($item) || $item === null) {
                return (string)$item;
            }
            return json_encode($item);
        }, array_values($value));
        $flat = array_filter($flat, fn($item) => $item !== '');
        return trim(implode(', ', $flat));
    }

    return (string)($value ?? '');
}
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=clients" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900 flex-1"><?php echo e($title); ?></h2>
        
        <!-- Removed duplicate save button (header) -->
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-12">
        <form id="client-form" class="p-8 space-y-8" method="POST">
            <input type="hidden" name="page" value="clients">
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Basic Info -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-[0.2em]">Full Name</label>
                    <input type="text" name="name" value="<?php echo e(clientFieldValue($client['name'] ?? '')); ?>" required
                           placeholder="e.g. John Doe"
                           class="w-full px-5 py-4 border-2 border-gray-50 rounded-2xl focus:border-black outline-none transition-all text-lg font-bold">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Company</label>
                    <input type="text" name="company" value="<?php echo e(clientFieldValue($client['company'] ?? '')); ?>"
                           placeholder="Acme Corp"
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Email Address</label>
                    <input type="email" name="email" value="<?php echo e(clientFieldValue($client['email'] ?? '')); ?>" required
                           placeholder="john@example.com"
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Phone Number</label>
                    <input type="text" name="phone" value="<?php echo e(clientFieldValue($client['phone'] ?? '')); ?>"
                           placeholder="+1 (555) 000-0000"
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Website</label>
                    <input type="text" name="website" value="<?php echo e(clientFieldValue($client['website'] ?? '')); ?>"
                           placeholder="https://..."
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                </div>
            </div>

            <!-- Address -->
            <div>
                <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Address</label>
                <textarea name="address" rows="3" 
                          placeholder="Street address, City, Country..."
                          class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-all min-h-[80px] font-medium"><?php echo e(clientFieldValue($client['address'] ?? '')); ?></textarea>
            </div>

            <!-- Client Brief / Notes -->
            <div class="pt-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-bold text-gray-400 uppercase tracking-widest">Client Brief / Internal Notes</label>
                    <button type="button" onclick="generateBriefWithAI()" class="text-[10px] font-black text-blue-600 hover:text-blue-800 flex items-center gap-1 uppercase tracking-tighter transition-colors">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                        AI Brief Generator
                    </button>
                </div>
                <textarea name="notes" rows="6" 
                          placeholder="Key info about this client, preferences, or project history..."
                          class="w-full px-4 py-4 border-2 border-gray-50 rounded-2xl focus:border-black outline-none transition-all min-h-[150px] font-medium"><?php echo e(clientFieldValue($client['notes'] ?? '')); ?></textarea>
                <p class="mt-2 text-[10px] text-gray-400 font-bold uppercase tracking-tight">AI can help summarize client details or create a "Ideal Client" brief.</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-4 pt-8 border-t border-gray-100 bg-white pb-4">
                <a href="?page=clients" class="px-8 py-3 text-gray-400 font-bold rounded-xl hover:text-black hover:bg-gray-50 transition uppercase tracking-widest text-sm border border-gray-200">
                    Cancel
                </a>
                <button type="submit" class="px-10 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition shadow-lg uppercase tracking-widest text-base">
                    <?php echo $id ? 'Save Changes' : 'Save Client'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Handler function - Global scope
async function handleSave(e) {
    if (e) e.preventDefault();
    
    const form = document.getElementById('client-form');
    
    // Basic client-side validation
    const submitBtns = document.querySelectorAll('button[type="submit"]');
    submitBtns.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner inline-block"></span> Saving...';
    });

    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Check if api exists, if not use fetch
        let response;
        if (typeof api !== 'undefined') {
            const url = data.id ? 'api/clients.php?action=update&id=' + data.id : 'api/clients.php?action=add';
            response = await api.post(url, data);
        } else {
            // Fallback implementation
            const url = data.id ? 'api/clients.php?action=update&id=' + data.id : 'api/clients.php?action=add';
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            response = await res.json();
        }
        
        if (response.success) {
            if (typeof showToast === 'function') {
                showToast(data.id ? 'Client updated!' : 'Client created!', 'success');
            } else {
                alert(data.id ? 'Client updated!' : 'Client created!');
            }
            setTimeout(() => location.href = '?page=clients', 1000);
        } else {
            const errorMsg = response.error?.message || response.error || 'Failed to save client';
            if (typeof showToast === 'function') {
                showToast(errorMsg, 'error');
            } else {
                alert(errorMsg);
            }
            resetButtons();
        }
    } catch (err) {
        console.error(err);
        if (typeof showToast === 'function') {
            showToast('An unexpected error occurred', 'error');
        } else {
            alert('An unexpected error occurred');
        }
        resetButtons();
    }
    
    return false; // Stop form submission
}

const clientForm = document.getElementById('client-form');
if (clientForm) {
    clientForm.addEventListener('submit', handleSave);
}

function resetButtons() {
    const form = document.getElementById('client-form');
    const submitBtns = document.querySelectorAll('button[type="submit"]');
    submitBtns.forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = form.querySelector('input[name="id"]').value ? 'Save Changes' : 'Save Client';
        // If it's the top button, reset to 'Save'
        if (btn.classList.contains('hidden')) { // The header button
             btn.innerHTML = 'Save';
        }
    });
}

// AI Generator
async function generateBriefWithAI() {
    // ... existing AI function ...
    const nameInput = document.querySelector('input[name="name"]');
    const companyInput = document.querySelector('input[name="company"]');
    const noteArea = document.querySelector('textarea[name="notes"]');
    
    if (!nameInput.value) {
        if (typeof showToast === 'function') showToast('Please enter a name first', 'info');
        else alert('Please enter a name first');
        return;
    }
    
    if (typeof showToast === 'function') showToast('AI is drafting a brief...', 'info');
    
    try {
        if (typeof api !== 'undefined') {
            const response = await api.post('api/ai-generate.php?action=brief', {
                name: nameInput.value,
                company: companyInput.value
            });
            if (response.success && response.data) {
                noteArea.value = response.data;
                if (typeof showToast === 'function') showToast('AI brief generated!', 'success');
            } else {
                throw new Error('Generation failed');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') showToast('AI generation failed', 'error');
        else alert('AI generation failed');
    }
}
</script>

