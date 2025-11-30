<div class="space-y-4 bg-gray-900 p-4 rounded-lg shadow-lg">
    @if($empty)
        <div class="p-6 bg-gray-800 rounded-lg border border-gray-700">
            <p class="text-gray-400 text-center text-lg">No contacts found for this customer.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Phone</th>
                    </tr>
                </thead>
                <tbody class="bg-gray-900 divide-y divide-gray-700">
                    @foreach($contacts as $contact)
                        <tr class="hover:bg-gray-800 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">{{ $contact->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $contact->email }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">+254{{ $contact->phone }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>