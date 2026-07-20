<?php
namespace App\Http\Controllers\Api\V1;
use App\Domain\Tenancy\Models\Note;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class NoteController extends Controller {
    public function index() { return response()->json(['data' => Note::latest()->get()]); }
    public function store(Request $r) {
        $data = $r->validate(['body' => 'required|string|max:500', 'workspace_id' => 'nullable|integer']);
        $data['user_id'] = $r->user()->id;
        return response()->json(['data' => Note::create($data)], 201);
    }
    public function show(Note $note) { return response()->json(['data' => $note]); }
    public function update(Request $r, Note $note) {
        $note->update($r->validate(['body' => 'required|string|max:500']));
        return response()->json(['data' => $note->fresh()]);
    }
    public function destroy(Note $note) { $note->delete(); return response()->json(['deleted' => true]); }
}
