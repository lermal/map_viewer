@include('errors.page', ['code' => $exception->getStatusCode(), 'title' => 'Server Error', 'message' => 'Something went wrong on our side. Please try again later.', 'retry' => true])
