---
tags: [skill-creator]
max_turns: 20
timeout_seconds: 600
allowed_tools: [Read, Glob, Grep, Skill, Write, Edit, Bash]
---

Can you do a security audit of this Laravel controller before we go live?

```php
class InvoiceController extends Controller
{
    public function show($id)
    {
        $invoice = Invoice::find($id);
        return view('invoices.show', ['invoice' => $invoice]);
    }

    public function search(Request $request)
    {
        $sort = $request->get('sort', 'created_at');
        $invoices = DB::select("SELECT * FROM invoices WHERE client_name LIKE '%" . $request->q . "%' ORDER BY $sort");
        return view('invoices.index', compact('invoices'));
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update($request->all());
        return redirect($request->input('return_url'));
    }

    public function download(Request $request)
    {
        return response()->download(storage_path('invoices/' . $request->file));
    }
}
```
and in invoices/show.blade.php: `<p>Notes: {!! $invoice->notes !!}</p>`
