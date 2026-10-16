<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DocumentType;

class DocumentTypeController extends Controller
{
    public function index(Request $request)
    {
        $documentTypes = DocumentType::all();
        
        if ($request->wantsJson()) {
            return response()->json($documentTypes);
        }

        return Inertia::render('DocumentTypes/Index', [
            'documentTypes' => $documentTypes
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        DocumentType::create($request->all());

        return redirect()->route('document-types.index')->with('success', 'Tipo de documento creado correctamente');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $documentType = DocumentType::findOrFail($id);
        $documentType->update($request->all());

        return redirect()->route('document-types.index')->with('success', 'Tipo de documento actualizado correctamente');
    }

    public function destroy($id)
    {
        $documentType = DocumentType::findOrFail($id);
        $documentType->delete();

        return redirect()->route('document-types.index')->with('success', 'Tipo de documento eliminado correctamente');
    }
}
