{{-- Live preview in the Template window: a sample card redrawn as each choice changes. --}}
<div>
    <div style="font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .35rem;">
        Preview <span style="font-weight: 400; color: #64748b;">— sample card for Mugizi Adrian; your real cards use your school's details</span>
    </div>
    <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 10px; padding: .4rem;">
        <iframe title="ID card preview" srcdoc="{{ view('id-cards.preview-frame', $sample)->render() }}" style="display: block; width: 100%; height: 260px; border: 0;" scrolling="no"></iframe>
    </div>
</div>
